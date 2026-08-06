<?php

namespace App\Http\Controllers\Api\PmsDepartment;

use App\Http\Controllers\Controller;
use App\Repositories\Pms\PmsDepartmentRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from Controllers/Pms_setup_department.php. */
class PmsDepartmentController extends Controller
{
    public function __construct(private readonly PmsDepartmentRepository $departments) {}

    /**
     * GET /api/pms-departments?...
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->departments->legacyTable(TableQuery::fromRequest($request));

        return response()->json([
            'data' => ['rows' => $result['rows'], 'meta' => $result['meta'], 'can_create_record' => true],
        ]);
    }

    /**
     * POST /api/pms-departments
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $data['name'] = strtoupper($data['name']);

        return response()->json(['data' => $this->departments->legacyCreate($data)], 201);
    }

    /**
     * PUT /api/pms-departments/{pmsDepartment}
     */
    public function update(Request $request, string $pmsDepartment): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $data['name'] = strtoupper($data['name']);

        return response()->json(['data' => $this->departments->legacyUpdate($pmsDepartment, $data)]);
    }

    /**
     * POST /api/pms-departments/{pmsDepartment}/toggle-status
     */
    public function toggleStatus(string $pmsDepartment): JsonResponse
    {
        return response()->json(['data' => $this->departments->legacyToggleStatus($pmsDepartment)]);
    }
}
