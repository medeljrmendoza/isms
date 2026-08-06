<?php

namespace App\Http\Controllers\Api\PmsClassification;

use App\Http\Controllers\Controller;
use App\Repositories\Pms\PmsClassificationRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from Controllers/Pms_setup_classification.php. */
class PmsClassificationController extends Controller
{
    public function __construct(private readonly PmsClassificationRepository $classifications) {}

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'departments' => $this->classifications->legacyDepartmentOptions(),
            'vessel_types' => $this->classifications->legacyVesselTypeOptions(),
            'can_create_record' => true,
        ]]);
    }

    public function index(Request $request): JsonResponse
    {
        $result = $this->classifications->legacyTable(
            $this->stringOrNull($request->query('department_id')),
            $this->stringOrNull($request->query('vessel_type_id')),
            TableQuery::fromRequest($request),
        );

        return response()->json(['data' => ['rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        return response()->json(['data' => $this->classifications->legacyCreate($data)], 201);
    }

    /**
     * GET /api/pms-classifications/{pmsClassification}
     */
    public function show(string $pmsClassification): JsonResponse
    {
        $detail = $this->classifications->legacyDetail($pmsClassification);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    public function update(Request $request, string $pmsClassification): JsonResponse
    {
        $data = $this->validateData($request);

        return response()->json(['data' => $this->classifications->legacyUpdate($pmsClassification, $data)]);
    }

    public function toggleStatus(string $pmsClassification): JsonResponse
    {
        return response()->json(['data' => $this->classifications->legacyToggleStatus($pmsClassification)]);
    }

    /**
     * GET /api/pms-classifications/{pmsClassification}/sub-classifications
     */
    public function subIndex(Request $request, string $pmsClassification): JsonResponse
    {
        $result = $this->classifications->legacySubTable($pmsClassification, TableQuery::fromRequest($request));

        return response()->json(['data' => [...$result, 'can_create_record' => true]]);
    }

    public function subStore(Request $request, string $pmsClassification): JsonResponse
    {
        $data = $this->validateSubData($request);

        return response()->json(['data' => $this->classifications->legacyCreateSub($pmsClassification, $data)], 201);
    }

    /**
     * GET /api/pms-sub-classifications/{pmsSubClassification}
     */
    public function subShow(string $pmsSubClassification): JsonResponse
    {
        return response()->json(['data' => $this->classifications->legacySubRow($pmsSubClassification)]);
    }

    public function subUpdate(Request $request, string $pmsSubClassification): JsonResponse
    {
        $data = $this->validateSubData($request);

        return response()->json(['data' => $this->classifications->legacyUpdateSub($pmsSubClassification, $data)]);
    }

    public function subToggleStatus(string $pmsSubClassification): JsonResponse
    {
        return response()->json(['data' => $this->classifications->legacyToggleSubStatus($pmsSubClassification)]);
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
            'department_ids.*' => 'string',
            'vessel_type_ids' => 'array',
            'vessel_type_ids.*' => 'string',
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
}
