<?php

namespace App\Repositories\IspsReview;

use App\Repositories\MasterReview\MasterReviewRepository;

use App\Models\IspsReview\IspsReview;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class IspsReviewRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'review_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'review_quarter', 'label' => 'QTY', 'sortable' => true],
        ['key' => 'review_year', 'label' => 'YEAR', 'sortable' => true],
        ['key' => 'sms', 'label' => 'PROCEDURE', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** Same shape/pattern as MasterReviewRepository — see its docblock. */
    public function pendingQuery(): Builder
    {
        return IspsReview::query()
            ->with(['vessel', 'manualDocument'])
            ->where('is_deleted', false)
            ->where('shore_status', '')
            ->where(function (Builder $query) {
                $query->where('added_by', 'SHORE')
                    ->orWhere(function (Builder $vessel) {
                        $vessel->where('added_by', 'VESSEL')->where('is_vessel_approved', true);
                    });
            });
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('review_date', 'like', $term)
                    ->orWhere('added_by', 'like', $term)
                    ->orWhere('review_year', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        // Legacy's own default order for this dashlet is review_year DESC (master_review defaults to review_date DESC).
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'review_year';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
