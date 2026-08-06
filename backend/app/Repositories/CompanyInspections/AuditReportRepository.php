<?php

namespace App\Repositories\CompanyInspections;

use App\Support\LegacyDb;
use App\Support\TableQuery;
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
     * Ported from Controllers/Company.php's loadData(), reading
     * tb_audit_report directly from the legacy connection. Keeps the
     * vessel_company='COMPANY' OR vesID-in-assigned-vessels scoping,
     * plus the vessel filter (legacy's sentinel vesID "NA" means
     * "company-wide reports only" — kept here as $vesselId === 'COMPANY').
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /** Ported from admin/company/view_company.php, reading tb_audit_report directly from the legacy connection. */
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
