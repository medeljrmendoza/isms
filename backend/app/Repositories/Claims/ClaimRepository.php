<?php

namespace App\Repositories\Claims;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

class ClaimRepository
{
    /** Matches the legacy DataTable's column list (minus Actions). "vessel" isn't a real sortable column — it's resolved from a relation. */
    private const COLUMNS = [
        ['key' => 'claim_no', 'label' => 'CLAIM NO.', 'sortable' => true],
        ['key' => 'claims_category', 'label' => 'CATEGORY', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'report_date', 'label' => 'DATE', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** Ported from Dashboard_claims.php's loadData(): open claims scoped to the logged-in user's assigned vessels. */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_jpi_records')
            ->where('status', '!=', 'CLOSED')
            ->whereIn('vesID', $assignedVesselIds);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('claim_no', 'like', $term)
                    ->orWhere('claims_category', 'like', $term)
                    ->orWhere('report_date', 'like', $term);
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'report_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($c) => [
            'claim_no' => $c->claim_no,
            'claims_category' => $c->claims_category,
            'vessel' => $vessels[$c->vesID] ?? '',
            'report_date' => $c->report_date,
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
}
