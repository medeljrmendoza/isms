<?php

namespace App\Http\Controllers\Api\PmsClassification;

use App\Http\Controllers\Controller;
use App\Models\Pms\PmsClassification;
use App\Models\Pms\PmsSubClassification;
use App\Repositories\Pms\PmsClassificationRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Ported from Controllers/Pms_setup_classification.php. */
class PmsClassificationController extends Controller
{
    public function __construct(private readonly PmsClassificationRepository $classifications) {}

    public function options(): JsonResponse
    {
        $legacy = LegacyDb::isConfigured();

        return response()->json(['data' => [
            'departments' => $legacy ? $this->classifications->legacyDepartmentOptions() : $this->classifications->departmentOptions(),
            'vessel_types' => $legacy ? $this->classifications->legacyVesselTypeOptions() : $this->classifications->vesselTypeOptions(),
            'can_create_record' => ! $legacy,
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->classifications->legacyTable(
                $this->stringOrNull($request->query('department_id')),
                $this->stringOrNull($request->query('vessel_type_id')),
                TableQuery::fromRequest($request),
            );

            return response()->json(['data' => ['rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $departmentId = $request->query('department_id') !== null ? (int) $request->query('department_id') : null;
        $vesselTypeId = $request->query('vessel_type_id') !== null ? (int) $request->query('vessel_type_id') : null;

        $paginator = $this->classifications->table($departmentId, $vesselTypeId, TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'rows' => collect($paginator->items())->map(fn (PmsClassification $c) => $this->mapRow($c))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $classification = $this->classifications->create($data);

        return response()->json(['data' => $this->mapDetail($this->classifications->detail($classification))], 201);
    }

    public function show(PmsClassification $pmsClassification): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->classifications->detail($pmsClassification))]);
    }

    public function update(Request $request, PmsClassification $pmsClassification): JsonResponse
    {
        $data = $this->validateData($request);
        $classification = $this->classifications->update($pmsClassification, $data);

        return response()->json(['data' => $this->mapDetail($this->classifications->detail($classification))]);
    }

    public function toggleStatus(PmsClassification $pmsClassification): JsonResponse
    {
        $classification = $this->classifications->toggleStatus($pmsClassification);

        return response()->json(['data' => $this->mapRow($classification->loadCount(['departments', 'vesselTypes', 'subClassifications']))]);
    }

    /**
     * GET /api/pms-classifications/{pmsClassification}/sub-classifications
     *
     * String param (not an Eloquent-bound model) so a legacy classID —
     * a string with no matching local row — can reach legacySubTable().
     */
    public function subIndex(Request $request, string $pmsClassification): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->classifications->legacySubTable($pmsClassification, TableQuery::fromRequest($request));

            return response()->json(['data' => [...$result, 'can_create_record' => false]]);
        }

        $classification = PmsClassification::query()->findOrFail((int) $pmsClassification);
        $paginator = $this->classifications->subTable($classification, TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'classification' => ['id' => $classification->id, 'name' => $classification->name],
                'rows' => collect($paginator->items())->map(fn (PmsSubClassification $s) => $this->mapSubRow($s))->all(),
                'meta' => $this->meta($paginator),
                'can_create_record' => true,
            ],
        ]);
    }

    public function subStore(Request $request, PmsClassification $pmsClassification): JsonResponse
    {
        $data = $this->validateSubData($request);
        $sub = $this->classifications->createSub($pmsClassification, $data);

        return response()->json(['data' => $this->mapSubRow($sub)], 201);
    }

    public function subShow(PmsSubClassification $pmsSubClassification): JsonResponse
    {
        return response()->json(['data' => $this->mapSubRow($pmsSubClassification)]);
    }

    public function subUpdate(Request $request, PmsSubClassification $pmsSubClassification): JsonResponse
    {
        $data = $this->validateSubData($request);
        $sub = $this->classifications->updateSub($pmsSubClassification, $data);

        return response()->json(['data' => $this->mapSubRow($sub)]);
    }

    public function subToggleStatus(PmsSubClassification $pmsSubClassification): JsonResponse
    {
        $sub = $this->classifications->toggleSubStatus($pmsSubClassification);

        return response()->json(['data' => $this->mapSubRow($sub)]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'department_ids' => 'array',
            'department_ids.*' => 'integer|exists:pms_departments,id',
            'vessel_type_ids' => 'array',
            'vessel_type_ids.*' => 'integer|exists:vessel_types,id',
        ]);

        $data['name'] = strtoupper($data['name']);

        return $data;
    }

    private function validateSubData(Request $request): array
    {
        $data = $request->validate([
            'chart_code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $data['chart_code'] = strtoupper($data['chart_code']);
        $data['name'] = strtoupper($data['name']);

        return $data;
    }

    private function mapRow(PmsClassification $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'description' => $c->description,
            'is_active' => $c->is_active,
            'departments' => $c->relationLoaded('departments') ? $c->departments->pluck('name')->all() : null,
            'vessel_types' => $c->relationLoaded('vesselTypes') ? $c->vesselTypes->pluck('name')->all() : null,
            'department_count' => $c->departments_count,
            'vessel_type_count' => $c->vessel_types_count,
            'sub_classification_count' => $c->sub_classifications_count,
            'can_edit' => true,
        ];
    }

    private function mapDetail(PmsClassification $c): array
    {
        return [
            'id' => $c->id,
            'name' => $c->name,
            'description' => $c->description,
            'is_active' => $c->is_active,
            'departments' => $c->departments->map(fn ($d) => ['id' => $d->id, 'name' => $d->name])->all(),
            'vessel_types' => $c->vesselTypes->map(fn ($v) => ['id' => $v->id, 'name' => $v->name])->all(),
        ];
    }

    private function mapSubRow(PmsSubClassification $s): array
    {
        return [
            'id' => $s->id,
            'pms_classification_id' => $s->pms_classification_id,
            'chart_code' => $s->chart_code,
            'name' => $s->name,
            'description' => $s->description,
            'is_active' => $s->is_active,
            'can_edit' => true,
        ];
    }

    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
