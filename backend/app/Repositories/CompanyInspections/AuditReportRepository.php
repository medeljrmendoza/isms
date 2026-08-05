<?php

namespace App\Repositories\CompanyInspections;

use App\Models\CompanyInspections\AuditKind;
use App\Models\CompanyInspections\AuditReport;
use App\Models\CompanyInspections\AuditType;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class AuditReportRepository
{
    /**
     * "vessel_company" (resolved vessel/company name) and "nc" (a
     * computed "pending/total" string) aren't real sortable columns.
     * "obs" isn't sortable either — see class docblock on why it's not
     * even a real count yet.
     */
    private const COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel_company', 'label' => 'VESSEL/COMPANY', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Company.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel_company', 'label' => 'VESSEL/COMPANY', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_audit', 'label' => 'PORT OF INSPECTION', 'sortable' => true],
        ['key' => 'audit_type', 'label' => 'TYPE', 'sortable' => false],
        ['key' => 'audit_kind', 'label' => 'KIND', 'sortable' => false],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function moduleColumns(): array
    {
        return self::MODULE_COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_company_inspections.php's
     * loadData(): audit reports that aren't deleted and have at least
     * one pending (open, active) non-conformity attributed to them.
     *
     * Two deliberate gaps vs. legacy, both agreed on in conversation:
     * - The legacy filter also shows a report if it has pending
     *   *Observations* — that module doesn't exist yet, so this only
     *   checks Nonconformities. A report with pending observations but
     *   zero pending NCs won't appear here yet.
     * - Legacy's COMPANY-scoped branch additionally required
     *   user_level != 'MEMBER'; we don't have roles yet, so company-wide
     *   reports are visible to everyone, consistent with vessel scoping
     *   being deferred the same way elsewhere.
     */
    public function pendingQuery(): Builder
    {
        return AuditReport::query()
            ->with('vessel')
            ->where('is_deleted', false)
            ->whereHas('nonconformities', function (Builder $q) {
                $q->where('is_inactive', false)->whereNull('close_out_date');
            })
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ]);
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('audit_ref', 'like', $term)
                    ->orWhere('this_date', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Dashboard_company_inspections.php's
     * loadData(): visible when (vessel_company='COMPANY', company-wide —
     * legacy also required user_level != 'MEMBER' here, dropped per the
     * same no-roles-yet precedent as the local `pendingQuery()`'s
     * docblock) OR (vessel-scoped to the logged-in user's assigned
     * vessels), AND has at least one pending NC or pending observation.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1');
        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_audit_report.auditID')
            ->where('is_deleted', '!=', '1')->where('status', '!=', 'COMPLETED');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_audit_report.auditID')->where('is_deleted', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_audit_report')
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('vessel_company', 'COMPANY')->orWhereIn('vesID', $assignedVesselIds);
            })
            ->where('is_deleted', '0')
            ->where(function ($q) {
                $q->whereIn('audit_ref', function ($sub) {
                    $sub->select('source_of_nc_ref_no')->from('tb_nonconformities')
                        ->where('is_inactive', '!=', '1')
                        ->where(function ($qq) {
                            $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
                        });
                })->orWhereIn('auditID', function ($sub) {
                    $sub->select('reportID')->from('tb_observations')
                        ->where('is_deleted', '!=', '1')->where('status', '!=', 'COMPLETED');
                });
            })
            ->select(['tb_audit_report.auditID', 'tb_audit_report.audit_ref', 'tb_audit_report.vessel_company', 'tb_audit_report.vesID', 'tb_audit_report.company', 'tb_audit_report.this_date'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc')
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_audit_report.audit_ref', 'like', $term)
                    ->orWhere('tb_audit_report.this_date', 'like', $term)
                    ->orWhere('tb_audit_report.company', 'like', $term);
            });
        }

        $sortMap = ['audit_ref' => 'tb_audit_report.audit_ref', 'this_date' => 'tb_audit_report.this_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_audit_report.this_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->auditID,
            'audit_ref' => $r->audit_ref,
            'vessel_company' => $r->vessel_company === 'VESSEL' ? ($vessels[$r->vesID] ?? '') : ($r->company ?? ''),
            'this_date' => $r->this_date,
            'nc' => $r->total_nc > 0 ? "{$r->pending_nc}/{$r->total_nc}" : '',
            'obs' => $r->total_obs > 0 ? "{$r->pending_obs}/{$r->total_obs}" : '',
        ])->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /**
     * Ported from Controllers/Company.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter. Legacy's sentinel vesID of "NA" means
     * "company-wide reports only" — kept here as $vesselId === 'COMPANY'.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = AuditReport::query()->with(['vessel', 'auditType', 'auditKind'])
            ->where('is_deleted', false)
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ]);

        if ($vesselId === 'COMPANY') {
            $builder->where('vessel_company', 'COMPANY');
        } elseif ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('vessel_id', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('audit_ref', 'like', $term)
                    ->orWhere('this_date', 'like', $term)
                    ->orWhere('placeof_audit', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('auditType', fn (Builder $t) => $t->where('name', 'like', $term))
                    ->orWhereHas('auditKind', fn (Builder $k) => $k->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'this_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Same as fullTable(), reading tb_audit_report directly from the
     * legacy connection. Keeps the vessel_company='COMPANY' OR vesID-in-
     * assigned-vessels scoping fullTable() drops (see its docblock).
     * Read-only: can_edit/can_delete are always false.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_audit_report')
            ->leftJoin('pl_audit_types', 'pl_audit_types.auditTypeID', '=', 'tb_audit_report.audit_type')
            ->leftJoin('pl_audit_kinds', 'pl_audit_kinds.auditKindID', '=', 'tb_audit_report.audit_kind')
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('tb_audit_report.vessel_company', 'COMPANY')->orWhereIn('tb_audit_report.vesID', $assignedVesselIds);
            })
            ->where('tb_audit_report.is_deleted', '0')
            ->select([
                'tb_audit_report.auditID', 'tb_audit_report.audit_ref', 'tb_audit_report.vessel_company',
                'tb_audit_report.vesID', 'tb_audit_report.company', 'tb_audit_report.this_date',
                'tb_audit_report.placeof_audit', 'pl_audit_types.audit_type_name', 'pl_audit_kinds.audit_kind_name',
            ])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc');

        if ($vesselId === 'COMPANY') {
            $builder->where('tb_audit_report.vessel_company', 'COMPANY');
        } elseif ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_audit_report.vesID', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_audit_report.audit_ref', 'like', $term)
                    ->orWhere('tb_audit_report.this_date', 'like', $term)
                    ->orWhere('tb_audit_report.placeof_audit', 'like', $term)
                    ->orWhere('tb_audit_report.company', 'like', $term);
            });
        }

        $sortMap = ['audit_ref' => 'tb_audit_report.audit_ref', 'this_date' => 'tb_audit_report.this_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_audit_report.this_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->auditID,
            'audit_ref' => $r->audit_ref,
            'vessel_company' => $r->vessel_company === 'VESSEL' ? ($vessels[$r->vesID] ?? '') : ($r->company ?? ''),
            'this_date' => $r->this_date,
            'placeof_audit' => $r->placeof_audit,
            'audit_type' => $r->audit_type_name,
            'audit_kind' => $r->audit_kind_name,
            'pending_nc_count' => $r->pending_nc,
            'total_nc_count' => $r->total_nc,
            'can_edit' => false,
            'can_delete' => false,
        ])->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** Ported from add_company_report()'s insert branch. */
    public function create(array $data): AuditReport
    {
        $data = $this->applyVesselCompany($data);

        return AuditReport::create([
            ...$data,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_company_report()'s update branch. vessel_company
     * and vessel_id are frozen at creation time (legacy re-reads both
     * from the existing row), but the company *name* stays editable —
     * legacy deliberately re-reads that one from the edit payload.
     *
     * When audit_ref changes, the new value cascades into any
     * Nonconformity rows linked by the old ref (loose string-key
     * relation — see AuditReport::nonconformities()) so they aren't
     * orphaned.
     */
    public function update(AuditReport $report, array $data): AuditReport
    {
        $oldRef = $report->audit_ref;

        unset($data['vessel_company'], $data['vessel_id']);

        if ($report->vessel_company === 'VESSEL') {
            unset($data['company']);
        }

        $report->update($data);

        if ($data['audit_ref'] !== $oldRef) {
            Nonconformity::where('source_of_nc_ref_no', $oldRef)->update(['source_of_nc_ref_no' => $data['audit_ref']]);
        }

        return $report;
    }

    /**
     * Ported from delete_company_report(): soft delete, plus cascades
     * deactivating any Nonconformity rows linked by this report's ref.
     */
    public function delete(AuditReport $report): void
    {
        $report->update(['is_deleted' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->audit_ref)->update(['is_inactive' => true]);
    }

    /**
     * A report is attributed to either a vessel or the company, never
     * both — legacy blanks whichever side doesn't apply.
     */
    private function applyVesselCompany(array $data): array
    {
        if (($data['vessel_company'] ?? null) === 'VESSEL') {
            $data['company'] = null;
        } else {
            $data['vessel_id'] = null;
        }

        return $data;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function auditTypeOptions(): array
    {
        return AuditType::query()->orderBy('name')->get()
            ->map(fn (AuditType $t) => ['id' => $t->id, 'label' => $t->name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function auditKindOptions(): array
    {
        return AuditKind::query()->orderBy('name')->get()
            ->map(fn (AuditKind $k) => ['id' => $k->id, 'label' => $k->name])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /**
     * Ported from admin/company/view_company.php, surfaced via the
     * dashboard's clickable audit_ref column. Read-only — see
     * SireReportRepository::detail()'s docblock for the convention.
     */
    public function detail(int $id): ?array
    {
        $r = AuditReport::query()->with(['vessel', 'auditType', 'auditKind'])
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ])
            ->find($id);

        if ($r === null) {
            return null;
        }

        return $this->toDetailArray([
            'id' => $r->id,
            'audit_ref' => $r->audit_ref,
            'vessel_company' => $r->vessel_company === 'VESSEL' ? ($r->vessel?->display_name ?? '') : ($r->company ?? ''),
            'vessel_company_raw' => $r->vessel_company,
            'this_date' => $r->this_date->format('Y-m-d'),
            'placeof_audit' => $r->placeof_audit,
            'audit_type' => $r->auditType?->name,
            'audit_kind' => $r->auditKind?->name,
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'company' => $r->company,
            'department' => $r->department,
            'inspector_name' => $r->inspector_name,
            'master_name' => $r->master_name,
            'chief_engineer' => $r->chief_engineer,
            'remarks' => $r->remarks,
        ]);
    }

    /** Same as detail(), reading tb_audit_report directly from the legacy connection. */
    public function legacyDetail(string $auditID): ?array
    {
        $r = DB::connection('legacy')->table('tb_audit_report')
            ->leftJoin('pl_audit_types', 'pl_audit_types.auditTypeID', '=', 'tb_audit_report.audit_type')
            ->leftJoin('pl_audit_kinds', 'pl_audit_kinds.auditKindID', '=', 'tb_audit_report.audit_kind')
            ->where('tb_audit_report.auditID', $auditID)
            ->select(['tb_audit_report.*', 'pl_audit_types.audit_type_name', 'pl_audit_kinds.audit_kind_name'])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();

        $pendingNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->audit_ref)->where('is_inactive', '!=', '1')
            ->where(function ($q) {
                $q->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            })->count();
        $totalNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->audit_ref)->where('is_inactive', '!=', '1')->count();

        return $this->toDetailArray([
            'id' => $r->auditID,
            'audit_ref' => $r->audit_ref,
            'vessel_company' => $r->vessel_company === 'VESSEL' ? ($vessels[$r->vesID] ?? '') : ($r->company ?? ''),
            'vessel_company_raw' => $r->vessel_company,
            'this_date' => $r->this_date,
            'placeof_audit' => $r->placeof_audit,
            'audit_type' => $r->audit_type_name,
            'audit_kind' => $r->audit_kind_name,
            'pending_nc_count' => $pendingNc,
            'total_nc_count' => $totalNc,
            'company' => $r->company,
            'department' => $r->department,
            'inspector_name' => LegacyDb::addressBookEntry($r->audit_auditor)['name'] ?? $r->audit_auditor,
            'master_name' => $r->audit_master,
            'chief_engineer' => $r->audit_chief_eng,
            'remarks' => $r->remarks,
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => $r['id'],
            'audit_ref' => $r['audit_ref'],
            'vessel_company' => $r['vessel_company'],
            'this_date' => $r['this_date'],
            'placeof_audit' => $r['placeof_audit'],
            'audit_type' => $r['audit_type'],
            'audit_kind' => $r['audit_kind'],
            'pending_nc_count' => $r['pending_nc_count'],
            'total_nc_count' => $r['total_nc_count'],
            'can_edit' => false,
            'can_delete' => false,
            'vessel_company_raw' => $r['vessel_company_raw'],
            'vessel_id' => null,
            'company' => $r['company'],
            'department' => $r['department'],
            'audit_type_id' => null,
            'audit_kind_id' => null,
            'inspector_name' => $r['inspector_name'],
            'master_name' => $r['master_name'],
            'chief_engineer' => $r['chief_engineer'],
            'remarks' => $r['remarks'],
        ];
    }
}
