<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsClassification;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsSubClassification;
use App\Models\VesselType;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ported from Controllers/Pms_setup_classification.php. Neither
 * classification nor sub-classification support delete in legacy —
 * only add/edit + status toggle — so no delete endpoints here either,
 * per the no-unreachable-actions convention.
 */
class PmsClassificationRepository
{
    /** @return array<int, array{id:int,label:string}> */
    public function departmentOptions(): array
    {
        return PmsDepartment::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (PmsDepartment $d) => ['id' => $d->id, 'label' => $d->name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselTypeOptions(): array
    {
        return VesselType::query()->where('is_active', true)->orderBy('name')->get()
            ->map(fn (VesselType $v) => ['id' => $v->id, 'label' => $v->name])
            ->all();
    }

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
     * Read-only — legacy classIDs are strings with no matching local row.
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
                'can_edit' => false,
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
            'can_edit' => false,
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

    public function table(?int $departmentId, ?int $vesselTypeId, TableQuery $query): LengthAwarePaginator
    {
        $builder = PmsClassification::query()
            ->withCount(['departments', 'vesselTypes', 'subClassifications'])
            ->with(['departments:id,name', 'vesselTypes:id,name']);

        if ($departmentId !== null) {
            $builder->whereHas('departments', fn ($q) => $q->where('pms_departments.id', $departmentId));
        }

        if ($vesselTypeId !== null) {
            $builder->whereHas('vesselTypes', fn ($q) => $q->where('vessel_types.id', $vesselTypeId));
        }

        if ($query->search !== null) {
            $builder->where('name', 'like', "%{$query->search}%");
        }

        $sortable = ['name' => 'name'];
        $sort = $sortable[$query->sort ?? 'name'] ?? 'name';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    public function detail(PmsClassification $classification): PmsClassification
    {
        return $classification->load(['departments', 'vesselTypes']);
    }

    /** Ported from add_classification()'s insert branch. */
    public function create(array $data): PmsClassification
    {
        $this->assertNameAvailable($data['name']);

        $classification = PmsClassification::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);

        $classification->departments()->sync($data['department_ids'] ?? []);
        $classification->vesselTypes()->sync($data['vessel_type_ids'] ?? []);

        return $classification;
    }

    /** Ported from add_classification()'s edit branch. */
    public function update(PmsClassification $classification, array $data): PmsClassification
    {
        if ($data['name'] !== $classification->name) {
            $this->assertNameAvailable($data['name']);
        }

        $classification->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        $classification->departments()->sync($data['department_ids'] ?? []);
        $classification->vesselTypes()->sync($data['vessel_type_ids'] ?? []);

        return $classification;
    }

    /** Ported from classification_status(). */
    public function toggleStatus(PmsClassification $classification): PmsClassification
    {
        $classification->update(['is_active' => ! $classification->is_active]);

        return $classification;
    }

    private function assertNameAvailable(string $name): void
    {
        if (PmsClassification::query()->where('name', $name)->exists()) {
            throw ValidationException::withMessages([
                'name' => ['CLASSIFICATION ALREADY EXIST!'],
            ]);
        }
    }

    public function subTable(PmsClassification $classification, TableQuery $query): LengthAwarePaginator
    {
        $builder = $classification->subClassifications();

        if ($query->search !== null) {
            $builder->where('name', 'like', "%{$query->search}%");
        }

        $sortable = ['chart_code' => 'chart_code', 'name' => 'name'];
        $sort = $sortable[$query->sort ?? 'chart_code'] ?? 'chart_code';

        return $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);
    }

    /** Ported from add_sub_classification()'s insert branch. */
    public function createSub(PmsClassification $classification, array $data): PmsSubClassification
    {
        $this->assertSubNameAvailable($classification, $data['name']);

        return $classification->subClassifications()->create([
            'chart_code' => $data['chart_code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => true,
        ]);
    }

    /** Ported from add_sub_classification()'s edit branch. */
    public function updateSub(PmsSubClassification $subClassification, array $data): PmsSubClassification
    {
        if ($data['name'] !== $subClassification->name) {
            $this->assertSubNameAvailable($subClassification->classification, $data['name'], $subClassification->id);
        }

        $subClassification->update([
            'chart_code' => $data['chart_code'],
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
        ]);

        return $subClassification;
    }

    /** Ported from sub_classification_status(). */
    public function toggleSubStatus(PmsSubClassification $subClassification): PmsSubClassification
    {
        $subClassification->update(['is_active' => ! $subClassification->is_active]);

        return $subClassification;
    }

    private function assertSubNameAvailable(PmsClassification $classification, string $name, ?int $excludingId = null): void
    {
        $exists = $classification->subClassifications()
            ->where('name', $name)
            ->when($excludingId, fn ($q) => $q->where('id', '!=', $excludingId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['SUB-CLASSIFICATION ALREADY EXIST!'],
            ]);
        }
    }
}
