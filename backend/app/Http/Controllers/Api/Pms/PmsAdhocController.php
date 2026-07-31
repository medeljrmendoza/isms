<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pms\PmsAdhocRequest;
use App\Models\Pms\PmsAdhoc;
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
        $vesselId = $this->intOrNull($request->query('vessel_id'));

        return response()->json([
            'data' => [
                'vessels' => $this->adhoc->vesselOptions(),
                'departments' => $this->adhoc->departmentOptions(),
                'job_classes' => $this->adhoc->jobClassOptions(),
                'job_types' => $this->adhoc->jobTypeOptions(),
                'components' => $vesselId ? $this->adhoc->componentOptions($vesselId) : [],
            ],
        ]);
    }

    /**
     * GET /api/pms-work-plan/parts?equipment_id=
     */
    public function parts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->adhoc->partOptions((int) $request->query('equipment_id')),
        ]);
    }

    /**
     * GET /api/pms-work-plan/search-parts?key=
     */
    public function searchParts(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->adhoc->searchParts((string) $request->query('key', '')),
        ]);
    }

    /**
     * GET /api/pms-work-plan?vessel_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $paginator = $this->adhoc->table((int) $request->query('vessel_id'), TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'columns' => PmsAdhocRepository::columns(),
                'rows' => collect($paginator->items())->map(fn (PmsAdhoc $a) => $this->mapRow($a))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/pms-work-plan/{adhoc}
     */
    public function show(PmsAdhoc $adhoc): JsonResponse
    {
        return response()->json(['data' => $this->adhoc->detail($adhoc)]);
    }

    /**
     * POST /api/pms-work-plan
     */
    public function store(PmsAdhocRequest $request): JsonResponse
    {
        $data = $request->validated();
        $inventory = $data['inventory'] ?? [];
        unset($data['inventory']);

        $adhoc = $this->adhoc->create($data, $inventory);

        return response()->json(['data' => $this->adhoc->detail($adhoc)], 201);
    }

    /**
     * PUT /api/pms-work-plan/{adhoc}
     */
    public function update(PmsAdhocRequest $request, PmsAdhoc $adhoc): JsonResponse
    {
        $data = $request->validated();
        $inventory = $data['inventory'] ?? [];
        unset($data['inventory']);

        $adhoc = $this->adhoc->update($adhoc, $data, $inventory);

        return response()->json(['data' => $this->adhoc->detail($adhoc)]);
    }

    /**
     * DELETE /api/pms-work-plan/{adhoc}
     */
    public function destroy(PmsAdhoc $adhoc): JsonResponse
    {
        $this->adhoc->delete($adhoc);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function mapRow(PmsAdhoc $a): array
    {
        return [
            'id' => $a->id,
            'ticket_no' => $a->ticket_no,
            'department' => $a->department?->name,
            'component' => $a->equipment?->equipment_name,
            'part' => $a->part?->part_name,
            'activity_name' => $a->activity_name,
            'incharge' => $a->incharge,
            'date_of_activity' => $a->date_of_activity->format('Y-m-d'),
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
