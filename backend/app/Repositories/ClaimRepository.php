<?php

namespace App\Repositories;

use App\Models\Claim;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

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

    /**
     * Ported from Controllers/Dashboard_claims.php's loadData() WHERE
     * clause: any claim not yet closed. Not scoped by vessel/user — same
     * deferral as NonconformityRepository.
     */
    public function openQuery(): Builder
    {
        return Claim::query()
            ->with('vessel')
            ->where('status', '!=', 'CLOSED');
    }

    public function open(): Collection
    {
        return $this->openQuery()->orderByDesc('report_date')->get();
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->openQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('claim_no', 'like', $term)
                    ->orWhere('claims_category', 'like', $term)
                    ->orWhere('report_date', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'report_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
