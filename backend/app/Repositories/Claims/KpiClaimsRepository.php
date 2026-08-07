<?php

namespace App\Repositories\Claims;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Kpi_claims.php. A pure reporting layer over
 * the legacy connection.
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
     * list, each scoped to the legacy user's ACTIVE assigned vessels,
     * exactly like legacy's own query does.
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
