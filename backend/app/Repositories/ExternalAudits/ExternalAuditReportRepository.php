<?php

namespace App\Repositories\ExternalAudits;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

class ExternalAuditReportRepository
{
    /** Same shape/caveats as AuditReportRepository — see its docblock. */
    private const COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/External.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_audit', 'label' => 'DATE OF AUDIT', 'sortable' => true],
        ['key' => 'portof_audit', 'label' => 'PORT OF AUDIT', 'sortable' => true],
        ['key' => 'typeof_audit', 'label' => 'TYPE', 'sortable' => true],
        ['key' => 'published', 'label' => 'PUBLISHED', 'sortable' => false],
        ['key' => 'is_approved', 'label' => 'APPROVED', 'sortable' => false],
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
     * Ported from Controllers/Dashboard_external.php's loadData() —
     * intentionally NOT a literal port of the raw SQL string there.
     * That WHERE clause is built as
     * `vesID_scoped AND is_deleted='0' AND (A) OR (B)` with no
     * enclosing parens around `(A) OR (B)`. Because SQL's AND binds
     * tighter than OR, this parses as
     * `(vesID_scoped AND is_deleted='0' AND A) OR (B)` — the OBS branch
     * (B) ends up with no vessel scoping or deleted-filter at all. Given
     * A and B are identical except NC-vs-OBS, this reads as a
     * copy-paste-missing-parens bug, not intentional design, so this
     * implements the clearly-intended version instead: one properly
     * grouped OR covering the approval trigger and the pending-NC
     * check (Observations deferred, same as the other audit dashlets).
     *
     * The approval trigger itself is real, unlike the other
     * audit-style dashlets: a report can show up purely because it
     * still needs approval, even with zero pending non-conformities.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_external_audit_report.ref_no')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_external_audit_report.ref_no')
            ->where('is_inactive', '!=', '1');
        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_external_audit_report.externalID')
            ->where('status', '!=', 'COMPLETED')->where('is_deleted', '0');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_external_audit_report.externalID')->where('is_deleted', '0');

        $needsApproval = function ($q) {
            $q->where(function ($shore) {
                $shore->where('added_by', 'SHORE')->where('is_published', '1')->where('is_approved', '0');
            })->orWhere(function ($vessel) {
                $vessel->where('added_by', 'VESSEL')->where('is_approved', '0');
            });
        };

        $builder = DB::connection('legacy')->table('tb_external_audit_report')
            ->where('is_deleted', '0')
            ->whereIn('vesID', $assignedVesselIds)
            ->where(function ($q) use ($needsApproval) {
                $needsApproval($q);
                $q->orWhereIn('ref_no', function ($sub) {
                    $sub->select('source_of_nc_ref_no')->from('tb_nonconformities')
                        ->where('is_inactive', '!=', '1')
                        ->where(function ($qq) {
                            $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
                        });
                })->orWhereIn('externalID', function ($sub) {
                    $sub->select('reportID')->from('tb_observations')
                        ->where('is_deleted', '0')->where('status', '!=', 'COMPLETED');
                });
            })
            ->select(['tb_external_audit_report.externalID', 'tb_external_audit_report.ref_no', 'tb_external_audit_report.vesID', 'tb_external_audit_report.dateof_audit'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc')
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_external_audit_report.ref_no', 'like', $term)
                    ->orWhere('tb_external_audit_report.dateof_audit', 'like', $term);
            });
        }

        $sortMap = ['ref_no' => 'tb_external_audit_report.ref_no', 'date' => 'tb_external_audit_report.dateof_audit'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_external_audit_report.dateof_audit';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->externalID,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'date' => $r->dateof_audit,
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
     * Ported from Controllers/External.php's loadData(), reading
     * tb_external_audit_report directly from the legacy connection,
     * scoped to the logged-in user's assigned vessels. Read-only:
     * can_edit/can_publish/can_approve/can_delete are always false.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_external_audit_report.ref_no')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_external_audit_report.ref_no')
            ->where('is_inactive', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_external_audit_report')
            ->where('is_deleted', '0')
            ->whereIn('vesID', $assignedVesselIds)
            ->select([
                'tb_external_audit_report.externalID', 'tb_external_audit_report.ref_no', 'tb_external_audit_report.vesID',
                'tb_external_audit_report.added_by', 'tb_external_audit_report.dateof_audit', 'tb_external_audit_report.portof_audit',
                'tb_external_audit_report.typeof_audit', 'tb_external_audit_report.is_published', 'tb_external_audit_report.is_approved',
            ])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc');

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_external_audit_report.vesID', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_external_audit_report.ref_no', 'like', $term)
                    ->orWhere('tb_external_audit_report.dateof_audit', 'like', $term)
                    ->orWhere('tb_external_audit_report.portof_audit', 'like', $term)
                    ->orWhere('tb_external_audit_report.typeof_audit', 'like', $term);
            });
        }

        $sortMap = ['ref_no' => 'tb_external_audit_report.ref_no', 'dateof_audit' => 'tb_external_audit_report.dateof_audit'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_external_audit_report.dateof_audit';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->externalID,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_audit' => $r->dateof_audit,
            'portof_audit' => $r->portof_audit,
            'typeof_audit' => $r->typeof_audit,
            'published' => $r->added_by === 'SHORE' ? $r->is_published === '1' : null,
            'is_approved' => $r->is_approved === '1',
            'pending_nc_count' => $r->pending_nc,
            'total_nc_count' => $r->total_nc,
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
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

    /** Ported from admin/external/view_external.php, reading tb_external_audit_report directly from the legacy connection. */
    public function legacyDetail(string $externalID): ?array
    {
        $r = DB::connection('legacy')->table('tb_external_audit_report')->where('externalID', $externalID)->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();

        $pendingNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)->where('is_inactive', '!=', '1')
            ->where(function ($q) {
                $q->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            })->count();
        $totalNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)->where('is_inactive', '!=', '1')->count();

        return $this->toDetailArray([
            'id' => $r->externalID,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_audit' => $r->dateof_audit,
            'portof_audit' => $r->portof_audit,
            'typeof_audit' => $r->typeof_audit,
            'published' => $r->added_by === 'SHORE' ? $r->is_published === '1' : null,
            'is_approved' => $r->is_approved === '1',
            'pending_nc_count' => $pendingNc,
            'total_nc_count' => $totalNc,
            'department' => $r->department,
            'master_name' => $r->master,
            'chief_engineer' => $r->chief_engineer,
            'auditor_name' => LegacyDb::addressBookEntry($r->auditor)['name'] ?? $r->auditor,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => $r['id'],
            'ref_no' => $r['ref_no'],
            'vessel' => $r['vessel'],
            'added_by' => $r['added_by'],
            'dateof_audit' => $r['dateof_audit'],
            'portof_audit' => $r['portof_audit'],
            'typeof_audit' => $r['typeof_audit'],
            'published' => $r['published'],
            'is_approved' => $r['is_approved'],
            'pending_nc_count' => $r['pending_nc_count'],
            'total_nc_count' => $r['total_nc_count'],
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_delete' => false,
            'vessel_id' => null,
            'department' => $r['department'],
            'master_name' => $r['master_name'],
            'chief_engineer' => $r['chief_engineer'],
            'auditor_name' => $r['auditor_name'],
            'shore_remarks' => $r['shore_remarks'],
            'vessel_remarks' => $r['vessel_remarks'],
        ];
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }
}
