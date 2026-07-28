<?php

namespace App\Repositories\CommitteeMeetings;

use App\Models\Vessel;

use App\Models\CommitteeMeetings\CommitteeMeeting;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class CommitteeMeetingRepository
{
    /**
     * "vessel" and "type" aren't real sortable columns — vessel is
     * relation-derived, and type is a computed multi-value label.
     */
    private const COLUMNS = [
        ['key' => 'meeting_date', 'label' => 'DATE', 'sortable' => true],
        // Legacy's own column header, even though it always resolves to
        // a vessel name here — the filter requires a vessel to be set.
        ['key' => 'vessel', 'label' => 'SHORE/VESSEL', 'sortable' => false],
        ['key' => 'type', 'label' => 'TYPE', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_committee_meeting.php's
     * loadData() WHERE clause: a meeting still needs shore remarks or
     * approval. Unlike most of the other dashlets, this one has no
     * Nonconformities/Observations dependency at all — self-contained.
     * Vessel scoping deferred as elsewhere.
     */
    public function pendingQuery(): Builder
    {
        return CommitteeMeeting::query()
            ->with(['vessel', 'meetingTypes'])
            ->where('is_deleted', false)
            ->where(function (Builder $query) {
                $needsAttention = fn (Builder $q) => $q->where('shore_remarks', '')->orWhere('is_approved', false);

                $query->where(function (Builder $shore) use ($needsAttention) {
                    $shore->where('added_by', 'SHORE')
                        ->where('is_published', true)
                        ->where($needsAttention);
                })->orWhere(function (Builder $vessel) use ($needsAttention) {
                    $vessel->where('added_by', 'VESSEL')
                        ->where($needsAttention);
                });
            });
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('meeting_date', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term))
                    ->orWhereHas('meetingTypes', fn (Builder $t) => $t->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'meeting_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
