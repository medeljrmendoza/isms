<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsClassification;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsSubClassification;
use App\Models\VesselType;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
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
