<?php

namespace App\Repositories\Defects;

use App\Models\Defects\Defect;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/** Ported from Controllers/Defect_list.php. */
class DefectRepository
{
    private const COLUMNS = [
        ['key' => 'sl_no', 'label' => 'SL NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'defect_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'priority', 'label' => 'PRIORITY', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => true],
        ['key' => 'compl_code', 'label' => 'COMPL CODE', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /**
     * Ported from Controllers/Dashboard_defect_list.php's loadData().
     * Legacy also scopes tb_defect_list.vesID to the user's assigned
     * vessels and requires tb_vessel.vessel_status='ACTIVE' via a join —
     * vessel/user scoping is deferred everywhere in this migration, so
     * only the compl_code exclusion (not yet marked Complete) remains.
     */
    public function pendingQuery(): Builder
    {
        return Defect::query()
            ->with('vessel')
            ->where('compl_code', '!=', 'C');
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('sl_no', 'like', $term)
                    ->orWhere('defect_date', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'defect_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Reads the dashlet's "pending" set from the real legacy staging
     * database instead of local seed data — see App\Support\LegacyDb.
     * Same not-yet-Complete rule as pendingQuery(); legacy's column
     * names differ from the local model's (defect_priority/defect_cat
     * vs priority/category), mapped 1:1 below. Also ported: legacy's
     * `vesID IN (SELECT vesID FROM tb_user_vessel WHERE userID = ...)
     * AND tb_vessel.vessel_status = 'ACTIVE'` scoping — unlike
     * Nonconformities there's no vesID='' fallback here, so a defect
     * with no vessel or an inactive vessel is simply never shown.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $eligibleVesselIds = LegacyDb::assignedVesselIds($legacyUserId)->intersect(LegacyDb::activeVesselIds());

        $builder = DB::connection('legacy')->table('tb_defect_list')
            ->where('compl_code', '!=', 'C')
            ->whereIn('vesID', $eligibleVesselIds);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('sl_no', 'like', $term)
                    ->orWhere('defect_date', 'like', $term)
                    ->orWhere('defect_priority', 'like', $term)
                    ->orWhere('defect_cat', 'like', $term);
            });
        }

        $columnMap = ['priority' => 'defect_priority', 'category' => 'defect_cat'];
        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'defect_date';
        $sort = $columnMap[$sort] ?? $sort;

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($d) => [
            'sl_no' => $d->sl_no,
            'vessel' => $vessels[$d->vesID] ?? '',
            'defect_date' => $d->defect_date,
            'priority' => $d->defect_priority,
            'category' => $d->defect_cat,
            'compl_code' => $d->compl_code,
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
     * Ported from index()/loadData(): vessel (ALL or specific), an
     * optional defect_date range, and an optional exact-match priority
     * filter. Legacy requires date_from+date_to together to apply the
     * date filter at all — mirrored here. Not ported: the user/vessel
     * assignment scoping (tb_user_vessel) and vessel_status='ACTIVE'
     * join, deferred everywhere in this migration. Default sort mirrors
     * index()'s "ORDER BY defect_date DESC, sl_no DESC".
     */
    public function fullTable(?int $vesselId, ?string $dateFrom, ?string $dateTo, ?string $priority, TableQuery $query): LengthAwarePaginator
    {
        $builder = Defect::query()->with('vessel');

        if ($vesselId !== null) {
            $builder->where('vessel_id', $vesselId);
        }

        if ($dateFrom !== null && $dateTo !== null) {
            $builder->whereDate('defect_date', '>=', $dateFrom)
                ->whereDate('defect_date', '<=', $dateTo);

            if ($priority !== null) {
                $builder->where('priority', $priority);
            }
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('sl_no', 'like', $term)
                    ->orWhere('defect_date', 'like', $term)
                    ->orWhere('priority', 'like', $term)
                    ->orWhere('category', 'like', $term)
                    ->orWhere('compl_code', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('present_status', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'defect_date';

        return $builder->orderBy($sort, $query->direction)
            ->orderBy('sl_no', $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /** Ported from add_record(): vesID is required to create a defect. */
    public function create(array $data): Defect
    {
        return Defect::create($data);
    }

    /**
     * Ported from add_record(): vesID and vessel_remarks are re-read
     * from the existing record rather than trusted from the form —
     * vessel_remarks has no admin-side write path (no VESSEL app in
     * this migration), matching the frozen-field pattern used elsewhere.
     */
    public function update(Defect $defect, array $data): Defect
    {
        unset($data['vessel_id'], $data['vessel_remarks']);
        $defect->update($data);

        return $defect;
    }

    /** Ported from add_record()'s delete-then-reinsert: hard delete here is the create/update boundary. */
    public function delete(Defect $defect): void
    {
        $defect->delete();
    }
}
