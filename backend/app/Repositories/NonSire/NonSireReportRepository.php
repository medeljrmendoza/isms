<?php

namespace App\Repositories\NonSire;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

class NonSireReportRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
        ['key' => 'pending', 'label' => 'PENDING', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Non_sire.php's
     * loadData(). "pending" (Observations) is dropped: that module
     * doesn't exist in this app. Like SIRE, there's no ref_no and so no
     * "NC" column either.
     */
    private const MODULE_COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
        ['key' => 'company_name', 'label' => 'COMPANY', 'sortable' => true],
        ['key' => 'inspector_name', 'label' => 'INSPECTOR', 'sortable' => true],
        ['key' => 'inspection_type', 'label' => 'INSPECTION TYPE', 'sortable' => true],
        ['key' => 'pass_fail', 'label' => 'PASS/FAIL', 'sortable' => true],
        ['key' => 'published', 'label' => 'PUBLISHED', 'sortable' => false],
        ['key' => 'is_approved', 'label' => 'APPROVED', 'sortable' => false],
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
     * Ported from Controllers/Dashboard_non_sire.php's loadData() —
     * same shape as SireReportRepository::legacyTable(), see its
     * docblock.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_non_sire.nonsireID')
            ->where('status', '!=', 'COMPLETED')->where('is_deleted', '0');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_non_sire.nonsireID')->where('is_deleted', '0');

        $builder = DB::connection('legacy')->table('tb_non_sire')
            ->where('is_deleted', '0')
            ->whereIn('vesID', $assignedVesselIds)
            ->where(function ($q) {
                $q->where(function ($qq) {
                    $qq->where('is_published', '1')->where('is_approved', '0');
                })->orWhereIn('nonsireID', function ($sub) {
                    $sub->select('reportID')->from('tb_observations')
                        ->where('status', '!=', 'COMPLETED')->where('is_deleted', '0');
                });
            })
            ->select(['tb_non_sire.nonsireID', 'tb_non_sire.vesID', 'tb_non_sire.dateof_inspection', 'tb_non_sire.placeof_inspection'])
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_non_sire.dateof_inspection', 'like', $term)
                    ->orWhere('tb_non_sire.placeof_inspection', 'like', $term);
            });
        }

        $sortMap = ['dateof_inspection' => 'tb_non_sire.dateof_inspection', 'placeof_inspection' => 'tb_non_sire.placeof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_non_sire.dateof_inspection';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->nonsireID,
            'vessel' => $vessels[$r->vesID] ?? '',
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'pending' => $r->total_obs > 0 ? "{$r->pending_obs}/{$r->total_obs}" : '',
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
     * Ported from Controllers/Non_sire.php's loadData(), reading
     * tb_non_sire directly from the legacy connection, scoped to the
     * logged-in user's assigned vessels. Read-only: can_edit/
     * can_publish/can_approve/can_delete are always false.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_non_sire')
            ->leftJoin('pl_non_sire_inspection_type', 'pl_non_sire_inspection_type.inspectionTypeID', '=', 'tb_non_sire.inspectionTypeID')
            ->where('tb_non_sire.is_deleted', '0')
            ->whereIn('tb_non_sire.vesID', $assignedVesselIds)
            ->select([
                'tb_non_sire.nonsireID', 'tb_non_sire.vesID', 'tb_non_sire.added_by', 'tb_non_sire.dateof_inspection',
                'tb_non_sire.placeof_inspection', 'tb_non_sire.company', 'tb_non_sire.inspector',
                'pl_non_sire_inspection_type.inspection_type as inspection_type_name',
                'tb_non_sire.pass_fail', 'tb_non_sire.is_published', 'tb_non_sire.is_approved',
            ]);

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_non_sire.vesID', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_non_sire.dateof_inspection', 'like', $term)
                    ->orWhere('tb_non_sire.placeof_inspection', 'like', $term)
                    ->orWhere('tb_non_sire.pass_fail', 'like', $term);
            });
        }

        $sortMap = ['dateof_inspection' => 'tb_non_sire.dateof_inspection', 'placeof_inspection' => 'tb_non_sire.placeof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_non_sire.dateof_inspection';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->nonsireID,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'company_name' => LegacyDb::addressBookEntry($r->company)['company'] ?? $r->company,
            'inspector_name' => LegacyDb::addressBookEntry($r->inspector)['name'] ?? $r->inspector,
            'inspection_type' => $r->inspection_type_name,
            'pass_fail' => $r->pass_fail,
            'published' => $r->added_by === 'SHORE' ? $r->is_published === '1' : null,
            'is_approved' => $r->is_published === '1' ? $r->is_approved === '1' : null,
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

    /** Ported from admin/non_sire/view_non_sire.php, reading tb_non_sire directly from the legacy connection. */
    public function legacyDetail(string $nonsireID): ?array
    {
        $r = DB::connection('legacy')->table('tb_non_sire')
            ->leftJoin('pl_non_sire_inspection_type', 'pl_non_sire_inspection_type.inspectionTypeID', '=', 'tb_non_sire.inspectionTypeID')
            ->where('tb_non_sire.nonsireID', $nonsireID)
            ->select(['tb_non_sire.*', 'pl_non_sire_inspection_type.inspection_type as inspection_type_name'])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();

        return $this->toDetailArray([
            'id' => $r->nonsireID,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_inspection' => $r->dateof_inspection,
            'placeof_inspection' => $r->placeof_inspection,
            'company_name' => LegacyDb::addressBookEntry($r->company)['company'] ?? $r->company,
            'inspector_name' => LegacyDb::addressBookEntry($r->inspector)['name'] ?? $r->inspector,
            'inspection_type' => $r->inspection_type_name,
            'pass_fail' => $r->pass_fail,
            'published' => $r->added_by === 'SHORE' ? $r->is_published === '1' : null,
            'is_approved' => $r->is_published === '1' ? $r->is_approved === '1' : null,
            'sire_cost' => $r->sire_cost,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => $r['id'],
            'vessel' => $r['vessel'],
            'added_by' => $r['added_by'],
            'dateof_inspection' => $r['dateof_inspection'],
            'placeof_inspection' => $r['placeof_inspection'],
            'company_name' => $r['company_name'],
            'inspector_name' => $r['inspector_name'],
            'inspection_type' => $r['inspection_type'],
            'pass_fail' => $r['pass_fail'],
            'published' => $r['published'],
            'is_approved' => $r['is_approved'],
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_delete' => false,
            'vessel_id' => null,
            'sire_cost' => $r['sire_cost'],
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
