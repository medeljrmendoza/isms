<?php

namespace App\Repositories\Claims;

use App\Models\Claims\Claim;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

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
}
