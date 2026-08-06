<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pms\PmsAdhocRequest;
use App\Models\Pms\PmsAdhoc;
use App\Repositories\Pms\PmsAdhocRepository;
use App\Support\LegacyDb;
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
        if (LegacyDb::isConfigured()) {
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
        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->adhoc->legacyPartOptions((string) $request->query('equipment_id'))]);
        }

        return response()->json([
            'data' => $this->adhoc->partOptions((int) $request->query('equipment_id')),
        ]);
    }

    /**
     * GET /api/pms-work-plan/search-parts?key=
     */
    public function searchParts(Request $request): JsonResponse
    {
        $key = (string) $request->query('key', '');

        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->adhoc->legacySearchParts($key)]);
        }

        return response()->json([
            'data' => $this->adhoc->searchParts($key),
        ]);
    }

    /**
     * GET /api/pms-work-plan?vessel_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $paginator = $this->adhoc->legacyTable((string) $request->query('vessel_id'), TableQuery::fromRequest($request));

            return response()->json([
                'data' => [
                    'columns' => PmsAdhocRepository::columns(),
                    'rows' => collect($paginator->items())->map(fn ($a) => $this->mapLegacyRow($a))->all(),
                    'meta' => $this->meta($paginator),
                ],
            ]);
        }

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
     *
     * String param (not an Eloquent-bound model) so a legacy adhocID —
     * a string with no matching local row — can reach legacyDetail().
     */
    public function show(string $adhoc): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->adhoc->legacyDetail($adhoc)]);
        }

        $model = PmsAdhoc::query()->findOrFail((int) $adhoc);

        return response()->json(['data' => $this->adhoc->detail($model)]);
    }

    /**
     * POST /api/pms-work-plan
     */
    public function store(PmsAdhocRequest $request): JsonResponse
    {
        $data = $request->validated();
        $inventory = $data['inventory'] ?? [];
        unset($data['inventory']);

        if (LegacyDb::isConfigured()) {
            $vesselId = $data['vessel_id'];
            unset($data['vessel_id']);

            return response()->json(['data' => $this->adhoc->legacyCreate($vesselId, $data, $inventory)], 201);
        }

        $adhoc = $this->adhoc->create($data, $inventory);

        return response()->json(['data' => $this->adhoc->detail($adhoc)], 201);
    }

    /**
     * PUT /api/pms-work-plan/{adhoc}
     */
    public function update(PmsAdhocRequest $request, string $adhoc): JsonResponse
    {
        $data = $request->validated();
        $inventory = $data['inventory'] ?? [];
        unset($data['inventory']);

        if (LegacyDb::isConfigured()) {
            return response()->json(['data' => $this->adhoc->legacyUpdate($adhoc, $data, $inventory)]);
        }

        $model = $this->adhoc->update(PmsAdhoc::query()->findOrFail((int) $adhoc), $data, $inventory);

        return response()->json(['data' => $this->adhoc->detail($model)]);
    }

    /**
     * DELETE /api/pms-work-plan/{adhoc}
     */
    public function destroy(string $adhoc): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $this->adhoc->legacyDelete($adhoc);

            return response()->json(['data' => ['ok' => true]]);
        }

        $this->adhoc->delete(PmsAdhoc::query()->findOrFail((int) $adhoc));

        return response()->json(['data' => ['ok' => true]]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
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
