<?php

namespace App\Repositories\InternalAudits;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

class InternalAuditReportRepository
{
    /** Same shape/caveats as AuditReportRepository — see its docblock. */
    private const COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Internal.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'audit_ref', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'this_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_audit', 'label' => 'PORT OF AUDIT', 'sortable' => true],
        ['key' => 'typeof_audit', 'label' => 'TYPE', 'sortable' => true],
        ['key' => 'auditor_name', 'label' => 'AUDITOR', 'sortable' => true],
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
     * Ported from Controllers/Dashboard_internal_audit_reports.php's
     * loadData(): visible when there's at least one pending NC or
     * pending observation, scoped to the logged-in user's assigned
     * vessels — same shape as PscReportRepository::legacyTable().
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_internal_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_internal_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1');
        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_internal_audit_report.auditID')
            ->where('is_deleted', '!=', '1')->where('status', '!=', 'COMPLETED');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_internal_audit_report.auditID')->where('is_deleted', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_internal_audit_report')
            ->where('is_deleted', '0')
            ->whereIn('vesID', $assignedVesselIds)
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
            ->select(['tb_internal_audit_report.auditID', 'tb_internal_audit_report.audit_ref', 'tb_internal_audit_report.vesID', 'tb_internal_audit_report.this_date'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc')
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_internal_audit_report.audit_ref', 'like', $term)
                    ->orWhere('tb_internal_audit_report.this_date', 'like', $term);
            });
        }

        $sortMap = ['audit_ref' => 'tb_internal_audit_report.audit_ref', 'this_date' => 'tb_internal_audit_report.this_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_internal_audit_report.this_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->auditID,
            'audit_ref' => $r->audit_ref,
            'vessel' => $vessels[$r->vesID] ?? '',
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
     * Ported from Controllers/Internal.php's loadData(), reading
     * tb_internal_audit_report directly from the legacy connection,
     * scoped to the logged-in user's assigned vessels. Unlike Company
     * Inspections, there's no "COMPANY" sentinel — internal audits are
     * always vessel-specific. Read-only: can_edit/can_delete are always
     * false.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_internal_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_internal_audit_report.audit_ref')
            ->where('is_inactive', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_internal_audit_report')
            ->where('is_deleted', '0')
            ->whereIn('vesID', $assignedVesselIds)
            ->select(['tb_internal_audit_report.auditID', 'tb_internal_audit_report.audit_ref', 'tb_internal_audit_report.vesID', 'tb_internal_audit_report.this_date', 'tb_internal_audit_report.placeof_audit', 'tb_internal_audit_report.typeof_audit', 'tb_internal_audit_report.audit_auditor'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc');

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_internal_audit_report.vesID', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_internal_audit_report.audit_ref', 'like', $term)
                    ->orWhere('tb_internal_audit_report.this_date', 'like', $term)
                    ->orWhere('tb_internal_audit_report.placeof_audit', 'like', $term)
                    ->orWhere('tb_internal_audit_report.typeof_audit', 'like', $term)
                    ->orWhere('tb_internal_audit_report.audit_auditor', 'like', $term);
            });
        }

        $sortMap = ['audit_ref' => 'tb_internal_audit_report.audit_ref', 'this_date' => 'tb_internal_audit_report.this_date'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_internal_audit_report.this_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->auditID,
            'audit_ref' => $r->audit_ref,
            'vessel' => $vessels[$r->vesID] ?? '',
            'this_date' => $r->this_date,
            'placeof_audit' => $r->placeof_audit,
            'typeof_audit' => $r->typeof_audit,
            'auditor_name' => $r->audit_auditor,
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

    /** Ported from admin/internal/view_internal.php, reading tb_internal_audit_report directly from the legacy connection. */
    public function legacyDetail(string $auditID): ?array
    {
        $r = DB::connection('legacy')->table('tb_internal_audit_report')->where('auditID', $auditID)->first();

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
            'vessel' => $vessels[$r->vesID] ?? '',
            'this_date' => $r->this_date,
            'placeof_audit' => $r->placeof_audit,
            'typeof_audit' => $r->typeof_audit,
            'auditor_name' => $r->audit_auditor,
            'pending_nc_count' => $pendingNc,
            'total_nc_count' => $totalNc,
            'department' => $r->department,
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
            'vessel' => $r['vessel'],
            'this_date' => $r['this_date'],
            'placeof_audit' => $r['placeof_audit'],
            'typeof_audit' => $r['typeof_audit'],
            'auditor_name' => $r['auditor_name'],
            'pending_nc_count' => $r['pending_nc_count'],
            'total_nc_count' => $r['total_nc_count'],
            'can_edit' => false,
            'can_delete' => false,
            'vessel_id' => null,
            'department' => $r['department'],
            'master_name' => $r['master_name'],
            'chief_engineer' => $r['chief_engineer'],
            'remarks' => $r['remarks'],
        ];
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }
}
