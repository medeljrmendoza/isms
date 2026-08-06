<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsDepartment;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Pms_setup_department.php. delete_department()
 * exists in legacy but has no reachable button in loadData()'s render
 * (only Edit + activate/inactivate) — dropped here per the
 * no-unreachable-actions convention used throughout this migration.
 */
class PmsDepartmentRepository
{
    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = PmsDepartment::query();

        if ($query->search !== null) {
            $builder->where('name', 'like', "%{$query->search}%");
        }

        $sortable = ['name' => 'name'];
        $sort = $sortable[$query->sort ?? 'name'] ?? 'name';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Pms_setup_department::loadData(), reading
     * tb_pms_department directly from the legacy connection. Read-only —
     * legacy deptIDs are strings with no matching local row.
     *
     * @return array{rows: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function legacyTable(TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_pms_department');

        if ($query->search !== null) {
            $builder->where('department_name', 'like', "%{$query->search}%");
        }

        $sortable = ['name' => 'department_name'];
        $sort = $sortable[$query->sort ?? 'name'] ?? 'department_name';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($d) => [
            'id' => $d->deptID,
            'name' => $d->department_name,
            'is_active' => (bool) $d->status,
            'can_edit' => false,
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

    /** Ported from add_department()'s insert branch. */
    public function create(array $data): PmsDepartment
    {
        return PmsDepartment::create([...$data, 'is_active' => true]);
    }

    /** Ported from add_department()'s edit branch. */
    public function update(PmsDepartment $department, array $data): PmsDepartment
    {
        $department->update($data);

        return $department;
    }

    /** Ported from edit_stat(): flips active/inactive. */
    public function toggleStatus(PmsDepartment $department): PmsDepartment
    {
        $department->update(['is_active' => ! $department->is_active]);

        return $department;
    }
}
