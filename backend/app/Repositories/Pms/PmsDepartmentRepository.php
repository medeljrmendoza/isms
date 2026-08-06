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
 * Add/Edit/toggle-status DO write to the legacy connection when reading
 * from legacy — legacy genuinely supports these actions on
 * tb_pms_department, so read-only-by-default doesn't apply here.
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

        $rows = collect($paginator->items())->map(fn ($d) => $this->mapLegacyRow($d))->all();

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

    /** Ported from add_department()'s insert branch, writing to the legacy connection. */
    public function legacyCreate(array $data): array
    {
        $deptId = 'pdept'.uniqid();

        DB::connection('legacy')->table('tb_pms_department')->insert([
            'deptID' => $deptId,
            'department_name' => $data['name'],
            'status' => 1,
            'datetime' => now(),
        ]);

        return $this->legacyFind($deptId);
    }

    /** Ported from add_department()'s edit branch, writing to the legacy connection. */
    public function legacyUpdate(string $deptId, array $data): array
    {
        DB::connection('legacy')->table('tb_pms_department')
            ->where('deptID', $deptId)
            ->update(['department_name' => $data['name']]);

        return $this->legacyFind($deptId);
    }

    /** Ported from edit_stat(): flips active/inactive on the legacy connection. */
    public function legacyToggleStatus(string $deptId): array
    {
        $legacy = DB::connection('legacy');
        $current = (int) $legacy->table('tb_pms_department')->where('deptID', $deptId)->value('status');

        $legacy->table('tb_pms_department')->where('deptID', $deptId)->update(['status' => $current ? 0 : 1]);

        return $this->legacyFind($deptId);
    }

    private function legacyFind(string $deptId): array
    {
        $d = DB::connection('legacy')->table('tb_pms_department')->where('deptID', $deptId)->first();

        return $this->mapLegacyRow($d);
    }

    private function mapLegacyRow(object $d): array
    {
        return [
            'id' => $d->deptID,
            'name' => $d->department_name,
            'is_active' => (bool) $d->status,
            'can_edit' => true,
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
