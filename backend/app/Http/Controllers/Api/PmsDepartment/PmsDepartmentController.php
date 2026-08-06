<?php

namespace App\Http\Controllers\Api\PmsDepartment;

use App\Http\Controllers\Controller;
use App\Models\Pms\PmsDepartment;
use App\Repositories\Pms\PmsDepartmentRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Ported from Controllers/Pms_setup_department.php. */
class PmsDepartmentController extends Controller
{
    public function __construct(private readonly PmsDepartmentRepository $departments) {}

    /**
     * GET /api/pms-departments?...
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->departments->legacyTable(TableQuery::fromRequest($request));

            return response()->json([
                'data' => ['rows' => $result['rows'], 'meta' => $result['meta'], 'can_create_record' => false],
            ]);
        }

        $paginator = $this->departments->table(TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'rows' => collect($paginator->items())->map(fn (PmsDepartment $d) => $this->mapRow($d))->all(),
                'meta' => $this->meta($paginator),
                'can_create_record' => true,
            ],
        ]);
    }

    /**
     * POST /api/pms-departments
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $data['name'] = strtoupper($data['name']);

        $department = $this->departments->create($data);

        return response()->json(['data' => $this->mapRow($department)], 201);
    }

    /**
     * PUT /api/pms-departments/{pmsDepartment}
     */
    public function update(Request $request, PmsDepartment $pmsDepartment): JsonResponse
    {
        $data = $request->validate(['name' => 'required|string|max:255']);
        $data['name'] = strtoupper($data['name']);

        $department = $this->departments->update($pmsDepartment, $data);

        return response()->json(['data' => $this->mapRow($department)]);
    }

    /**
     * POST /api/pms-departments/{pmsDepartment}/toggle-status
     */
    public function toggleStatus(PmsDepartment $pmsDepartment): JsonResponse
    {
        $department = $this->departments->toggleStatus($pmsDepartment);

        return response()->json(['data' => $this->mapRow($department)]);
    }

    private function mapRow(PmsDepartment $d): array
    {
        return [
            'id' => $d->id,
            'name' => $d->name,
            'is_active' => $d->is_active,
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
