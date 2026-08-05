<?php

namespace App\Repositories\Claims;

use App\Models\Claims\Claim;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Kpi_claims.php. A pure reporting layer over
 * the already-migrated Claim data — no new tables beyond the
 * nature_diagnosis/amount_usd columns added for the drill-down lists
 * (see the add_nature_diagnosis_and_amount_usd_to_claims_table
 * migration). Unlike legacy, which hardcodes a fixed 4-code category
 * list (CARGO / 3RD PARTY / HNM / PNI CREW), claims_category here holds
 * free descriptive text (see ClaimSeeder) that doesn't match those
 * codes — so, same decision as KpiNonSireRepository's inspection_type,
 * this groups by the distinct category values actually present in the
 * data instead of a fixed lookup list.
 */
class KpiClaimsRepository
{
    private const REPORT_COLUMNS = [
        ['key' => 'claim_no', 'label' => 'CLAIM NO.', 'sortable' => true],
        ['key' => 'claims_category', 'label' => 'CATEGORY', 'sortable' => true],
        ['key' => 'report_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nature_diagnosis', 'label' => 'NATURE / DIAGNOSIS', 'sortable' => false],
        ['key' => 'status', 'label' => 'STATUS', 'sortable' => true],
        ['key' => 'amount_usd', 'label' => 'AMOUNT (USD)', 'sortable' => true],
    ];

    private const CATEGORY_COLUMNS = [
        ['key' => 'claim_no', 'label' => 'CLAIM NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'report_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nature_diagnosis', 'label' => 'NATURE / DIAGNOSIS', 'sortable' => false],
        ['key' => 'status', 'label' => 'STATUS', 'sortable' => true],
        ['key' => 'amount_usd', 'label' => 'AMOUNT (USD)', 'sortable' => true],
    ];

    public static function reportColumns(): array
    {
        return self::REPORT_COLUMNS;
    }

    public static function categoryColumns(): array
    {
        return self::CATEGORY_COLUMNS;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** Ported from index()'s filter==0 branch. */
    public function claimsPerVessel(?string $from, ?string $to): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => [
                'label' => $v->display_name,
                'count' => $this->scopeDateRange(Claim::query()->where('vessel_id', $v->id), $from, $to)->count(),
            ])
            ->all();
    }

    /**
     * Ported from index()'s filter==1 branch. Legacy iterates a fixed
     * 4-code category list (including codes with zero claims); without
     * that list, this groups over the distinct claims_category values
     * actually present in the data instead — see class docblock.
     */
    public function claimsPerCategory(?string $from, ?string $to): array
    {
        $categories = Claim::query()
            ->whereNotNull('claims_category')
            ->distinct()
            ->orderBy('claims_category')
            ->pluck('claims_category');

        return $categories->map(fn (string $category) => [
            'label' => $category,
            'count' => $this->scopeDateRange(Claim::query()->where('claims_category', $category), $from, $to)->count(),
        ])->all();
    }

    /** Ported from loadClaimsVesselData(). */
    public function claimsByVessel(int $vesselId, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(Claim::query()->where('vessel_id', $vesselId), $from, $to);

        $sortable = array_column(array_filter(self::REPORT_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'report_date';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /** Ported from loadClaimsCategoryData(). */
    public function claimsByCategory(string $category, ?string $from, ?string $to, TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->scopeDateRange(Claim::query()->with('vessel')->where('claims_category', $category), $from, $to);

        $sortable = array_column(array_filter(self::CATEGORY_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'report_date';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    private function scopeDateRange(Builder $builder, ?string $from, ?string $to, string $column = 'report_date'): Builder
    {
        if ($from !== null && $from !== '') {
            return $builder->where($column, '>=', $from)->where($column, '<=', $to ?: $from);
        }

        return $builder->whereYear($column, Carbon::now()->year);
    }

    /** Ported from index()'s hardcoded $category_list: raw code => display label. */
    private const LEGACY_CATEGORY_LABELS = [
        'CARGO' => 'CARGO',
        '3RD PARTY' => '3RD PARTY',
        'HNM' => 'H&M',
        'PNI CREW' => 'P&I CREW',
    ];

    /**
     * Ported from Kpi_claims::index()'s $vessels query: ACTIVE vessels
     * the given legacy user is assigned to, sorted by name.
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

    /** Ported from index()'s filter==0 branch, reading tb_jpi_records directly. */
    public function legacyClaimsPerVessel(?string $from, ?string $to, ?string $legacyUserId): array
    {
        return collect($this->legacyVesselOptions($legacyUserId))
            ->map(function (array $v) use ($from, $to) {
                $q = DB::connection('legacy')->table('tb_jpi_records')->where('vesID', $v['id']);
                $this->legacyScopeDateRange($q, $from, $to);

                return ['label' => $v['label'], 'count' => $q->count()];
            })->all();
    }

    /**
     * Ported from index()'s filter==1 branch: the fixed 4-code category
     * list (unlike the local simplification — see class docblock), each
     * also scoped to the legacy user's ACTIVE assigned vessels, exactly
     * like legacy's own query does.
     */
    public function legacyClaimsPerCategory(?string $from, ?string $to, ?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        return collect(self::LEGACY_CATEGORY_LABELS)->map(function (string $label, string $code) use ($from, $to, $assignedVesselIds) {
            $q = DB::connection('legacy')->table('tb_jpi_records')
                ->join('tb_vessel', 'tb_vessel.vesID', '=', 'tb_jpi_records.vesID')
                ->where('tb_jpi_records.claims_category', $code)
                ->where('tb_vessel.vessel_status', 'ACTIVE')
                ->whereIn('tb_vessel.vesID', $assignedVesselIds);
            $this->legacyScopeDateRange($q, $from, $to, 'tb_jpi_records.report_date');

            return ['label' => $label, 'count' => $q->count()];
        })->values()->all();
    }

    /** Ported from loadClaimsVesselData(). */
    public function legacyClaimsByVessel(string $vesselId, ?string $from, ?string $to, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_jpi_records')
            ->where('vesID', $vesselId)
            ->select(['jpiID', 'claim_no', 'claims_category', 'report_date', 'nature_diagnosis', 'status'])
            ->selectSub(fn ($q) => $q->from('tb_jpi_billing_records')->selectRaw('SUM(usd_amount)')->whereColumn('jpiID', 'tb_jpi_records.jpiID'), 'sum_usd');
        $this->legacyScopeDateRange($builder, $from, $to, 'report_date');

        $sortMap = ['claim_no' => 'claim_no', 'claims_category' => 'claims_category', 'report_date' => 'report_date', 'status' => 'status', 'amount_usd' => 'sum_usd'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'report_date';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->jpiID,
            'claim_no' => $r->claim_no,
            'claims_category' => self::LEGACY_CATEGORY_LABELS[$r->claims_category] ?? $r->claims_category,
            'report_date' => $r->report_date,
            'nature_diagnosis' => $r->nature_diagnosis,
            'status' => $r->status,
            'amount_usd' => $r->sum_usd,
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    /**
     * Ported from loadClaimsCategoryData(): maps the human display label
     * back to legacy's raw code — unlike claimsPerCategory(), this has
     * no vessel/user scoping in legacy either.
     */
    public function legacyClaimsByCategory(string $category, ?string $from, ?string $to, TableQuery $query): array
    {
        $code = array_search($category, self::LEGACY_CATEGORY_LABELS, true) ?: $category;
        $vessels = LegacyDb::vesselNames();

        $builder = DB::connection('legacy')->table('tb_jpi_records')
            ->where('claims_category', $code)
            ->select(['jpiID', 'claim_no', 'vesID', 'report_date', 'nature_diagnosis', 'status'])
            ->selectSub(fn ($q) => $q->from('tb_jpi_billing_records')->selectRaw('SUM(usd_amount)')->whereColumn('jpiID', 'tb_jpi_records.jpiID'), 'sum_usd');
        $this->legacyScopeDateRange($builder, $from, $to, 'report_date');

        $sortMap = ['claim_no' => 'claim_no', 'report_date' => 'report_date', 'status' => 'status', 'amount_usd' => 'sum_usd'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'report_date';
        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->jpiID,
            'claim_no' => $r->claim_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'report_date' => $r->report_date,
            'nature_diagnosis' => $r->nature_diagnosis,
            'status' => $r->status,
            'amount_usd' => $r->sum_usd,
        ])->all();

        return ['rows' => $rows, 'meta' => $this->legacyMeta($paginator)];
    }

    private function legacyScopeDateRange(QueryBuilder $builder, ?string $from, ?string $to, string $column = 'report_date'): QueryBuilder
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
