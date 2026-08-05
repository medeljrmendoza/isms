<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsDepartment;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;

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
