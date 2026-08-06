<?php

namespace App\Repositories\Tasks;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

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
     * Ported from Controllers/Dashboard_tasks.php's
     * loadAssignedTaskTable(): SHORE TO SHORE or SHORE TO VESSEL tasks
     * the logged-in legacy user created, not yet approved or deleted,
     * and either company-wide (blank vesID) or scoped to one of their
     * assigned vessels.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $builder = DB::connection('legacy')->table('tb_task')
            ->leftJoin('tb_task_category', 'tb_task_category.taskCategoryID', '=', 'tb_task.categoryID')
            ->whereIn('tb_task.task_type', ['SHORE TO SHORE', 'SHORE TO VESSEL'])
            ->where('tb_task.created_by', $legacyUserId ?? '')
            ->where('tb_task.is_approved', '0')
            ->where('tb_task.is_deleted', '0')
            ->where(function ($q) use ($assignedVesselIds) {
                $q->where('tb_task.vesID', '')->orWhereIn('tb_task.vesID', $assignedVesselIds);
            })
            ->select([
                'tb_task.taskID',
                'tb_task_category.category',
                'tb_task.reference_tag',
                'tb_task.due_date',
                'tb_task.priority',
                'tb_task.task_status',
            ]);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_task.taskID', 'like', $term)
                    ->orWhere('tb_task_category.category', 'like', $term)
                    ->orWhere('tb_task.reference_tag', 'like', $term)
                    ->orWhere('tb_task.due_date', 'like', $term)
                    ->orWhere('tb_task.priority', 'like', $term)
                    ->orWhere('tb_task.task_status', 'like', $term);
            });
        }

        $sortMap = [
            'task_no' => 'tb_task.taskID',
            'category' => 'tb_task_category.category',
            'reference_tag' => 'tb_task.reference_tag',
            'due_date' => 'tb_task.due_date',
            'priority' => 'tb_task.priority',
            'task_status' => 'tb_task.task_status',
        ];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_task.due_date';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'task_no' => $r->taskID,
            'category' => $r->category ?? '',
            'reference_tag' => $r->reference_tag ?? '',
            'due_date' => $r->due_date,
            'priority' => $r->priority,
            'task_status' => $r->task_status,
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
