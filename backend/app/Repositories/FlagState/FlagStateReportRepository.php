<?php

namespace App\Repositories\FlagState;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

class FlagStateReportRepository
{
    private const COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Flag_state.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'inspector', 'label' => 'INSPECTOR', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
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
     * Ported from Controllers/Dashboard_flag_state.php's loadData():
     * visible when (still needs approval) OR (has a pending NC) OR (has
     * a pending observation), scoped to the logged-in user's assigned
     * vessels. Same missing-parens fix as ExternalAuditReportRepository
     * — see its docblock for the full explanation.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_flag_state.ref_no')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_flag_state.ref_no')
            ->where('is_inactive', '!=', '1');
        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_flag_state.flagID')
            ->where('status', '!=', 'COMPLETED')->where('is_deleted', '0');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_flag_state.flagID')->where('is_deleted', '0');

        $needsApproval = function ($q) {
            $q->where(function ($shore) {
                $shore->where('added_by', 'SHORE')->where('is_published', '1')->where('is_approved', '0');
            })->orWhere(function ($vessel) {
                $vessel->where('added_by', 'VESSEL')->where('is_approved', '0');
            });
        };

        $builder = DB::connection('legacy')->table('tb_flag_state')
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
                })->orWhereIn('flagID', function ($sub) {
                    $sub->select('reportID')->from('tb_observations')
                        ->where('is_deleted', '!=', '1')->where('status', '!=', 'COMPLETED');
                });
            })
            ->select(['tb_flag_state.flagID', 'tb_flag_state.ref_no', 'tb_flag_state.vesID', 'tb_flag_state.dateof_inspection'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc')
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_flag_state.ref_no', 'like', $term)
                    ->orWhere('tb_flag_state.dateof_inspection', 'like', $term);
            });
        }

        $sortMap = ['ref_no' => 'tb_flag_state.ref_no', 'date' => 'tb_flag_state.dateof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_flag_state.dateof_inspection';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->flagID,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'date' => $r->dateof_inspection,
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
     * Ported from Controllers/Flag_state.php's loadData(), reading
     * tb_flag_state directly from the legacy connection, scoped to the
     * logged-in user's assigned vessels. Read-only: can_edit/
     * can_publish/can_approve/can_delete are always false.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_flag_state.ref_no')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_flag_state.ref_no')
            ->where('is_inactive', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_flag_state')
            ->where('is_deleted', '0')
            ->whereIn('vesID', $assignedVesselIds)
            ->select([
                'tb_flag_state.flagID', 'tb_flag_state.ref_no', 'tb_flag_state.vesID', 'tb_flag_state.added_by',
                'tb_flag_state.dateof_inspection', 'tb_flag_state.placeof_inspection', 'tb_flag_state.inspector',
                'tb_flag_state.is_published', 'tb_flag_state.is_approved',
            ])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc');

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_flag_state.vesID', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_flag_state.ref_no', 'like', $term)
                    ->orWhere('tb_flag_state.dateof_inspection', 'like', $term)
                    ->orWhere('tb_flag_state.inspector', 'like', $term)
                    ->orWhere('tb_flag_state.placeof_inspection', 'like', $term);
            });
        }

        $sortMap = ['ref_no' => 'tb_flag_state.ref_no', 'dateof_inspection' => 'tb_flag_state.dateof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_flag_state.dateof_inspection';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->flagID,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'inspector' => $r->inspector,
            'published' => $r->added_by === 'SHORE' ? $r->is_published === '1' : null,
            'is_approved' => $r->is_published === '1' ? $r->is_approved === '1' : null,
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

    /** Ported from admin/flag_state/view_flag_state.php, reading tb_flag_state directly from the legacy connection. */
    public function legacyDetail(string $flagID): ?array
    {
        $r = DB::connection('legacy')->table('tb_flag_state')->where('flagID', $flagID)->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();

        $pendingNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)
            ->where('is_inactive', '!=', '1')
            ->where(function ($q) {
                $q->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            })->count();
        $totalNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)
            ->where('is_inactive', '!=', '1')
            ->count();

        return $this->toDetailArray([
            'id' => $r->flagID,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'inspector' => $r->inspector,
            'published' => $r->added_by === 'SHORE' ? $r->is_published === '1' : null,
            'is_approved' => $r->is_published === '1' ? $r->is_approved === '1' : null,
            'pending_nc_count' => $pendingNc,
            'total_nc_count' => $totalNc,
            'flag_cost' => $r->flag_cost,
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
            'dateof_inspection' => $r['dateof_inspection'],
            'placeof_inspection' => $r['placeof_inspection'],
            'inspector' => $r['inspector'],
            'published' => $r['published'],
            'is_approved' => $r['is_approved'],
            'pending_nc_count' => $r['pending_nc_count'],
            'total_nc_count' => $r['total_nc_count'],
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_delete' => false,
            'vessel_id' => null,
            'flag_cost' => $r['flag_cost'],
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
