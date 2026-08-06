<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pms\PmsAdhocRequest;
use App\Repositories\Pms\PmsAdhocRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/** Ported from Controllers/Pms_work_plan.php — see PmsAdhocRepository's docblock for what wasn't ported. */
class PmsAdhocController extends Controller
{
    public function __construct(private readonly PmsAdhocRepository $adhoc) {}

    /**
     * GET /api/pms-work-plan/options?vessel_id=
     */
    public function options(Request $request): JsonResponse
    {
        $vesselId = $this->stringOrNull($request->query('vessel_id'));

        return response()->json([
            'data' => [
                'vessels' => $this->adhoc->legacyVesselOptions(),
                'departments' => $this->adhoc->legacyDepartmentOptions(),
                'job_classes' => $this->adhoc->legacyJobClassOptions(),
                'job_types' => $this->adhoc->legacyJobTypeOptions(),
                'components' => $vesselId ? $this->adhoc->legacyComponentOptions($vesselId) : [],
            ],
        ]);
    }

    /**
     * GET /api/pms-work-plan/parts?equipment_id=
     */
    public function parts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->adhoc->legacyPartOptions((string) $request->query('equipment_id'))]);
    }

    /**
     * GET /api/pms-work-plan/search-parts?key=
     */
    public function searchParts(Request $request): JsonResponse
    {
        $key = (string) $request->query('key', '');

        return response()->json(['data' => $this->adhoc->legacySearchParts($key)]);
    }

    /**
     * GET /api/pms-work-plan?vessel_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adhoc->legacyTable((string) $request->query('vessel_id'), TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'columns' => PmsAdhocRepository::columns(),
                'rows' => collect($paginator->items())->map(fn ($a) => $this->mapLegacyRow($a))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/pms-work-plan/{adhoc}
     */
    public function show(string $adhoc): JsonResponse
    {
        return response()->json(['data' => $this->adhoc->legacyDetail($adhoc)]);
    }

    /**
     * POST /api/pms-work-plan
     */
    public function store(PmsAdhocRequest $request): JsonResponse
    {
        $data = $request->validated();
        $inventory = $data['inventory'] ?? [];
        unset($data['inventory']);

        $vesselId = $data['vessel_id'];
        unset($data['vessel_id']);

        return response()->json(['data' => $this->adhoc->legacyCreate($vesselId, $data, $inventory)], 201);
    }

    /**
     * PUT /api/pms-work-plan/{adhoc}
     */
    public function update(PmsAdhocRequest $request, string $adhoc): JsonResponse
    {
        $data = $request->validated();
        $inventory = $data['inventory'] ?? [];
        unset($data['inventory']);

        return response()->json(['data' => $this->adhoc->legacyUpdate($adhoc, $data, $inventory)]);
    }

    /**
     * DELETE /api/pms-work-plan/{adhoc}
     */
    public function destroy(string $adhoc): JsonResponse
    {
        $this->adhoc->legacyDelete($adhoc);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function mapLegacyRow(object $a): array
    {
        return [
            'id' => $a->adhocID,
            'ticket_no' => $a->adhocID,
            'department' => $a->department_name,
            'component' => $a->equipment_name,
            'part' => $a->part_name,
            'activity_name' => $a->work_plan_activity,
            'incharge' => $a->incharge,
            'date_of_activity' => $a->dateof_activity,
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
