<?php

namespace App\Repositories;

use App\Models\Task;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class TaskRepository
{
    /** Matches the legacy DataTable's column list (sender/receiver notif columns drove row-highlight styling only, not shown here). */
    private const COLUMNS = [
        ['key' => 'task_no', 'label' => 'TASK ID', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => true],
        ['key' => 'reference_tag', 'label' => 'REFERENCE/TAG', 'sortable' => true],
        ['key' => 'due_date', 'label' => 'DUE', 'sortable' => true],
        ['key' => 'priority', 'label' => 'PRIORITY', 'sortable' => true],
        ['key' => 'task_status', 'label' => 'STATUS', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_tasks.php's loadAssignedTaskTable()
     * WHERE clause: SHORE TO SHORE or SHORE TO VESSEL tasks the given
     * user created, not yet approved or deleted. Unlike the other
     * dashlets, this scoping (created_by) isn't the deferred
     * vessel-permissions kind — it's core to what "assigned by me" means,
     * and we have a real authenticated user via Sanctum to filter by.
     * Vessel scoping (tb_user_vessel) is still deferred as elsewhere.
     */
    public function assignedByQuery(int $userId): Builder
    {
        return Task::query()
            ->whereIn('task_type', ['SHORE TO SHORE', 'SHORE TO VESSEL'])
            ->where('created_by', $userId)
            ->where('is_approved', false)
            ->where('is_deleted', false);
    }

    public function table(TableQuery $query, int $userId): LengthAwarePaginator
    {
        $builder = $this->assignedByQuery($userId);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('task_no', 'like', $term)
                    ->orWhere('category', 'like', $term)
                    ->orWhere('reference_tag', 'like', $term)
                    ->orWhere('due_date', 'like', $term)
                    ->orWhere('priority', 'like', $term)
                    ->orWhere('task_status', 'like', $term);
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'due_date';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
