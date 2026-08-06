<?php

namespace App\Repositories\Pms;

use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ported from Controllers/Pms_setup_classification.php. Neither
 * classification nor sub-classification support delete in legacy —
 * only add/edit + status toggle — so no delete endpoints here either,
 * per the no-unreachable-actions convention. Add/Edit/toggle-status
 * write to the legacy connection — legacy genuinely supports these
 * actions, so read-only-by-default doesn't apply here.
 */
class PmsClassificationRepository
{
    /** @return array<int, array{id:string,label:string}> */
    public function legacyDepartmentOptions(): array
    {
        return DB::connection('legacy')->table('tb_pms_department')
            ->where('status', 1)
            ->orderBy('department_name')
            ->get()
            ->map(fn ($d) => ['id' => $d->deptID, 'label' => $d->department_name])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselTypeOptions(): array
    {
        return DB::connection('legacy')->table('pl_vessel_type')
            ->where('is_inactive', 'N')
            ->orderBy('vessel_type')
            ->get()
            ->map(fn ($v) => ['id' => $v->vestypeID, 'label' => $v->vessel_type])
            ->all();
    }

    /**
     * Ported from Pms_setup_classification::loadClassificationData(),
     * reading tb_pms_classification directly from the legacy connection.
     *
     * @return array{rows: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function legacyTable(?string $departmentId, ?string $vesselTypeId, TableQuery $query): array
    {
        $legacy = DB::connection('legacy');
        $builder = $legacy->table('tb_pms_classification');

        if ($departmentId !== null) {
            $builder->whereIn('classID', $legacy->table('tb_pms_classification_department')->where('deptID', $departmentId)->select('classID'));
        }

        if ($vesselTypeId !== null) {
            $builder->whereIn('classID', $legacy->table('tb_pms_classification_vessel_type')->where('vestypeID', $vesselTypeId)->select('classID'));
        }

        if ($query->search !== null) {
            $builder->where('classification_name', 'like', "%{$query->search}%");
        }

        $sortable = ['name' => 'classification_name'];
        $sort = $sortable[$query->sort ?? 'name'] ?? 'classification_name';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $classIds = collect($paginator->items())->pluck('classID')->all();

        $subCounts = $legacy->table('tb_pms_sub_classification')
            ->whereIn('classID', $classIds)
            ->selectRaw('classID, COUNT(*) as cnt')
            ->groupBy('classID')
            ->pluck('cnt', 'classID');

        $departmentsByClass = $legacy->table('tb_pms_classification_department')
            ->join('tb_pms_department', 'tb_pms_department.deptID', '=', 'tb_pms_classification_department.deptID')
            ->whereIn('tb_pms_classification_department.classID', $classIds)
            ->select('tb_pms_classification_department.classID', 'tb_pms_department.department_name')
            ->get()
            ->groupBy('classID');

        $vesselTypesByClass = $legacy->table('tb_pms_classification_vessel_type')
            ->join('pl_vessel_type', 'pl_vessel_type.vestypeID', '=', 'tb_pms_classification_vessel_type.vestypeID')
            ->whereIn('tb_pms_classification_vessel_type.classID', $classIds)
            ->select('tb_pms_classification_vessel_type.classID', 'pl_vessel_type.vessel_type')
            ->get()
            ->groupBy('classID');

        $rows = collect($paginator->items())->map(function ($c) use ($subCounts, $departmentsByClass, $vesselTypesByClass) {
            $departments = ($departmentsByClass->get($c->classID) ?? collect())->pluck('department_name')->all();
            $vesselTypes = ($vesselTypesByClass->get($c->classID) ?? collect())->pluck('vessel_type')->all();

            return [
                'id' => $c->classID,
                'name' => $c->classification_name,
                'description' => $c->description,
                'is_active' => (bool) $c->status,
                'departments' => $departments,
                'vessel_types' => $vesselTypes,
                'department_count' => count($departments),
                'vessel_type_count' => count($vesselTypes),
                'sub_classification_count' => (int) ($subCounts[$c->classID] ?? 0),
                'can_edit' => true,
            ];
        })->all();

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

    /** Ported from view_classification(), reading the legacy connection. */
    public function legacyDetail(string $classId): ?array
    {
        $legacy = DB::connection('legacy');
        $c = $legacy->table('tb_pms_classification')->where('classID', $classId)->first();

        if ($c === null) {
            return null;
        }

        $departments = $legacy->table('tb_pms_classification_department')
            ->join('tb_pms_department', 'tb_pms_department.deptID', '=', 'tb_pms_classification_department.deptID')
            ->where('tb_pms_classification_department.classID', $classId)
            ->select('tb_pms_department.deptID as id', 'tb_pms_department.department_name as name')
            ->get()
            ->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])
            ->all();

        $vesselTypes = $legacy->table('tb_pms_classification_vessel_type')
            ->join('pl_vessel_type', 'pl_vessel_type.vestypeID', '=', 'tb_pms_classification_vessel_type.vestypeID')
            ->where('tb_pms_classification_vessel_type.classID', $classId)
            ->select('pl_vessel_type.vestypeID as id', 'pl_vessel_type.vessel_type as name')
            ->get()
            ->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])
            ->all();

        return [
            'id' => $c->classID,
            'name' => $c->classification_name,
            'description' => $c->description,
            'is_active' => (bool) $c->status,
            'departments' => $departments,
            'vessel_types' => $vesselTypes,
        ];
    }

    /** Ported from add_classification()'s insert branch, writing to the legacy connection. */
    public function legacyCreate(array $data): array
    {
        $this->assertLegacyNameAvailable($data['name']);

        $classId = 'class'.uniqid();
        $this->legacySaveClassification($classId, $data, 1);

        return $this->legacyRow($classId);
    }

    /** Ported from add_classification()'s edit branch, writing to the legacy connection. */
    public function legacyUpdate(string $classId, array $data): array
    {
        $legacy = DB::connection('legacy');
        $current = $legacy->table('tb_pms_classification')->where('classID', $classId)->first();

        if (($current->classification_name ?? null) !== $data['name']) {
            $this->assertLegacyNameAvailable($data['name']);
        }

        $this->legacySaveClassification($classId, $data, (int) ($current->status ?? 1));

        return $this->legacyRow($classId);
    }

    /**
     * Ported from add_classification()'s save branch: delete-then-reinsert
     * the classification row and both M:M pivots.
     */
    private function legacySaveClassification(string $classId, array $data, int $status): void
    {
        $legacy = DB::connection('legacy');

        $legacy->table('tb_pms_classification')->where('classID', $classId)->delete();
        $legacy->table('tb_pms_classification_department')->where('classID', $classId)->delete();

        foreach ($data['department_ids'] ?? [] as $deptId) {
            $legacy->table('tb_pms_classification_department')->insert([
                'classdeptID' => 'classdept'.uniqid(),
                'classID' => $classId,
                'deptID' => $deptId,
            ]);
        }

        $legacy->table('tb_pms_classification_vessel_type')->where('classID', $classId)->delete();

        foreach ($data['vessel_type_ids'] ?? [] as $vestypeId) {
            $legacy->table('tb_pms_classification_vessel_type')->insert([
                'classvestypeID' => 'classvestype'.uniqid(),
                'classID' => $classId,
                'vestypeID' => $vestypeId,
            ]);
        }

        $legacy->table('tb_pms_classification')->insert([
            'classID' => $classId,
            'classification_name' => $data['name'],
            'description' => $data['description'] ?? '',
            'status' => $status,
        ]);
    }

    /** Ported from classification_status(): flips active/inactive on the legacy connection. */
    public function legacyToggleStatus(string $classId): array
    {
        $legacy = DB::connection('legacy');
        $current = (int) $legacy->table('tb_pms_classification')->where('classID', $classId)->value('status');

        $legacy->table('tb_pms_classification')->where('classID', $classId)->update(['status' => $current ? 0 : 1]);

        return $this->legacyRow($classId);
    }

    private function assertLegacyNameAvailable(string $name): void
    {
        $exists = DB::connection('legacy')->table('tb_pms_classification')->where('classification_name', $name)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['CLASSIFICATION ALREADY EXIST!'],
            ]);
        }
    }

    private function legacyRow(string $classId): array
    {
        $legacy = DB::connection('legacy');
        $c = $legacy->table('tb_pms_classification')->where('classID', $classId)->first();

        $departments = $legacy->table('tb_pms_classification_department')
            ->join('tb_pms_department', 'tb_pms_department.deptID', '=', 'tb_pms_classification_department.deptID')
            ->where('tb_pms_classification_department.classID', $classId)
            ->pluck('tb_pms_department.department_name')
            ->all();

        $vesselTypes = $legacy->table('tb_pms_classification_vessel_type')
            ->join('pl_vessel_type', 'pl_vessel_type.vestypeID', '=', 'tb_pms_classification_vessel_type.vestypeID')
            ->where('tb_pms_classification_vessel_type.classID', $classId)
            ->pluck('pl_vessel_type.vessel_type')
            ->all();

        return [
            'id' => $c->classID,
            'name' => $c->classification_name,
            'description' => $c->description,
            'is_active' => (bool) $c->status,
            'departments' => $departments,
            'vessel_types' => $vesselTypes,
            'department_count' => count($departments),
            'vessel_type_count' => count($vesselTypes),
            'sub_classification_count' => $legacy->table('tb_pms_sub_classification')->where('classID', $classId)->count(),
            'can_edit' => true,
        ];
    }

    /**
     * Ported from Pms_setup_classification::loadSubClassificationData(),
     * reading tb_pms_sub_classification directly from the legacy
     * connection.
     *
     * @return array{classification: array{id:string,name:string}, rows: array<int, array<string, mixed>>, meta: array<string, int>}
     */
    public function legacySubTable(string $classId, TableQuery $query): array
    {
        $legacy = DB::connection('legacy');

        $classification = $legacy->table('tb_pms_classification')->where('classID', $classId)->first();

        $builder = $legacy->table('tb_pms_sub_classification')->where('classID', $classId);

        if ($query->search !== null) {
            $builder->where('sub_classification_name', 'like', "%{$query->search}%");
        }

        $sortable = ['chart_code' => 'chart_code', 'name' => 'sub_classification_name'];
        $sort = $sortable[$query->sort ?? 'chart_code'] ?? 'chart_code';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($s) => [
            'id' => $s->subClassID,
            'pms_classification_id' => $s->classID,
            'chart_code' => $s->chart_code,
            'name' => $s->sub_classification_name,
            'description' => $s->description,
            'is_active' => (bool) $s->status,
            'can_edit' => true,
        ])->all();

        return [
            'classification' => ['id' => $classId, 'name' => $classification->classification_name ?? ''],
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** Ported from add_sub_classification()'s insert branch, writing to the legacy connection. */
    public function legacyCreateSub(string $classId, array $data): array
    {
        $this->assertLegacySubNameAvailable($classId, $data['name']);

        $subId = 'subclass'.uniqid();
        $this->legacySaveSub($subId, $classId, $data, 1);

        return $this->legacySubRow($subId);
    }

    /** Ported from add_sub_classification()'s edit branch, writing to the legacy connection. */
    public function legacyUpdateSub(string $subId, array $data): array
    {
        $legacy = DB::connection('legacy');
        $current = $legacy->table('tb_pms_sub_classification')->where('subClassID', $subId)->first();
        $classId = $current->classID;

        if (($current->sub_classification_name ?? null) !== $data['name']) {
            $this->assertLegacySubNameAvailable($classId, $data['name'], $subId);
        }

        $this->legacySaveSub($subId, $classId, $data, (int) ($current->status ?? 1));

        return $this->legacySubRow($subId);
    }

    /** Ported from add_sub_classification()'s save branch: delete-then-reinsert. */
    private function legacySaveSub(string $subId, string $classId, array $data, int $status): void
    {
        $legacy = DB::connection('legacy');

        $legacy->table('tb_pms_sub_classification')->where('subClassID', $subId)->delete();

        $legacy->table('tb_pms_sub_classification')->insert([
            'subClassID' => $subId,
            'classID' => $classId,
            'chart_code' => $data['chart_code'],
            'sub_classification_name' => $data['name'],
            'description' => $data['description'] ?? '',
            'status' => $status,
        ]);
    }

    /** Ported from sub_classification_status(): flips active/inactive on the legacy connection. */
    public function legacyToggleSubStatus(string $subId): array
    {
        $legacy = DB::connection('legacy');
        $current = (int) $legacy->table('tb_pms_sub_classification')->where('subClassID', $subId)->value('status');

        $legacy->table('tb_pms_sub_classification')->where('subClassID', $subId)->update(['status' => $current ? 0 : 1]);

        return $this->legacySubRow($subId);
    }

    private function assertLegacySubNameAvailable(string $classId, string $name, ?string $excludingId = null): void
    {
        $exists = DB::connection('legacy')->table('tb_pms_sub_classification')
            ->where('classID', $classId)
            ->where('sub_classification_name', $name)
            ->when($excludingId, fn ($q) => $q->where('subClassID', '!=', $excludingId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['SUB-CLASSIFICATION ALREADY EXIST!'],
            ]);
        }
    }

    public function legacySubRow(string $subId): array
    {
        $s = DB::connection('legacy')->table('tb_pms_sub_classification')->where('subClassID', $subId)->first();

        return [
            'id' => $s->subClassID,
            'pms_classification_id' => $s->classID,
            'chart_code' => $s->chart_code,
            'name' => $s->sub_classification_name,
            'description' => $s->description,
            'is_active' => (bool) $s->status,
            'can_edit' => true,
        ];
    }
}
