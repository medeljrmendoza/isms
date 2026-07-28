<?php

namespace App\Repositories\MasterReview;

use App\Repositories\CommitteeMeetings\CommitteeMeetingRepository;
use App\Models\Vessel;

use App\Models\MasterReview\MasterReview;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class MasterReviewRepository
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

    /**
     * Ported from Controllers/Dashboard_master_review.php's loadData().
     * Same SHORE-auto-include / VESSEL-needs-approval pattern as
     * CommitteeMeetingRepository, plus the shore_status='' gate (once
     * shore acts on it — approve/disapprove/disregard/recommend, all
     * omitted here since editing actions are out of scope — it drops
     * off this list). Vessel/user scoping deferred as everywhere else.
     */
    public function pendingQuery(): Builder
    {
        return MasterReview::query()
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
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('manualDocument', fn (Builder $d) => $d->where('reference_no', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'review_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
