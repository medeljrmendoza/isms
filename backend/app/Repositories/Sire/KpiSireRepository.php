<?php

namespace App\Repositories\Sire;

use App\Models\Sire\SireReport;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Kpi_sire.php. Only "Reports per Vessel"
 * (filter==0) is portable — the other three legacy filters
 * (Observations per Chapter, Observations per Vessel, Observations per
 * Chapter-per-Vessel) are entirely built on tb_observations, and no
 * Observations module exists anywhere in this migration (same
 * convention as KpiPscInspectionsRepository/KpiFlagStateRepository).
 * Unlike PSC/Flag State, SIRE has no linked-Nonconformity concept
 * either — legacy's own SIRE KPI never queries tb_nonconformities.
 */
class KpiSireRepository
{
    private const REPORT_COLUMNS = [
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => false],
        ['key' => 'company_name', 'label' => 'COMPANY', 'sortable' => false],
        ['key' => 'inspector_name', 'label' => 'INSPECTOR', 'sortable' => false],
    ];

    public static function reportColumns(): array
    {
        return self::REPORT_COLUMNS;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** Ported from index()'s filter==0 branch. */
    public function reportsPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => $this->scopeDateRange(SireReport::query()->where('vessel_id', $v->id)->where('is_deleted', false), $from, $to)->count(),
            ])
            ->all();
    }

    /** Ported from loadSireReportsVesselData(). */
    public function reportsByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(
            SireReport::query()->where('vessel_id', $vesselId)->where('is_deleted', false),
            $from,
            $to,
        );

        $sortable = array_column(array_filter(self::REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    private function scopeDateRange(Builder $builder, ?string $from, ?string $to, string $column = 'dateof_inspection'): Builder
    {
        if ($from !== null && $from !== '') {
            return $builder->where($column, '>=', $from)->where($column, '<=', $to ?: $from);
        }

        return $builder->whereYear($column, Carbon::now()->year);
    }

    /**
     * Ported from Kpi_sire::index()'s $vessels query: ACTIVE vessels the
     * given legacy user is assigned to, sorted by name.
     *
     * @return array<int, array{id:string,label:string}>
     */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        $names = LegacyDb::vesselNames();
        $ids = LegacyDb::assignedVesselIds($legacyUserId)->intersect(LegacyDb::activeVesselIds());

        return $ids->map(fn ($id) => ['id' => $id, 'label' => $names[$id] ?? ''])
            ->sortBy('label')->values()->all();
    }

    /** Ported from index()'s filter==0 branch, reading tb_sire directly. */
    public function legacyReportsPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_sire')
                    ->where('vesID', $v['id'])->where('is_deleted', '0');
                $this->legacyScopeDateRange($q, $from, $to);

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /** Ported from loadSireReportsVesselData(). */
    public function legacyReportsByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_sire')
            ->where('vesID', $vesselId)->where('is_deleted', '0')
            ->select(['sireID', 'dateof_inspection', 'added_by', 'placeof_inspection', 'company', 'inspector']);
        $this->legacyScopeDateRange($builder, $from, $to);

        $sortMap = ['dateof_inspection' => 'dateof_inspection', 'added_by' => 'added_by'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'dateof_inspection';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->sireID,
            'dateof_inspection' => $r->dateof_inspection,
            'added_by' => $r->added_by,
            'placeof_inspection' => $r->placeof_inspection,
            'company_name' => (LegacyDb::addressBookEntry($r->company) ?? [])['company'] ?? '',
            'inspector_name' => (LegacyDb::addressBookEntry($r->inspector) ?? [])['name'] ?? '',
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    private function legacyScopeDateRange(QueryBuilder $builder, ?string $from, ?string $to, string $column = 'dateof_inspection'): QueryBuilder
    {
        if ($from !== null && $from !== '') {
            return $builder->where($column, '>=', $from)->where($column, '<=', $to ?: $from);
        }

        return $builder->whereYear($column, Carbon::now()->year);
    }

    private function legacyMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
