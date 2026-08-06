<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pms\PmsActivityMarkDoneRequest;
use App\Http\Requests\Pms\PmsActivityPostponeRequest;
use App\Repositories\Pms\PmsActivitiesRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Pms_activities.php — see PmsActivitiesRepository's
 * docblock for what wasn't ported. Not ported here either: the tb_logs
 * audit trail, matching every other module in this migration.
 */
class PmsActivitiesController extends Controller
{
    public function __construct(private readonly PmsActivitiesRepository $activities) {}

    /**
     * GET /api/pms-activities/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->activities->legacyVesselOptions(),
                'departments' => $this->activities->legacyDepartmentOptions(),
                'criticalities' => $this->activities->legacyCriticalityOptions(),
                'main_groups' => $this->activities->legacyMainGroupOptions(),
            ],
        ]);
    }

    /**
     * GET /api/pms-activities?vessel_id=&year=&department_id=&criticality_id=&main_group_id=&search=
     */
    public function index(Request $request): JsonResponse
    {
        $year = $this->intOrNull($request->query('year'));
        $vesselId = (string) $request->query('vessel_id');

        return response()->json([
            'data' => [
                'current_period' => $this->activities->legacyCurrentPeriod($vesselId),
                'year_options' => array_values(array_unique(array_filter([
                    $this->activities->legacyCurrentYear($vesselId),
                    ...$this->activities->legacyHistoricalYears($vesselId),
                ]))),
                'rows' => $this->activities->legacyTable(
                    $vesselId,
                    $year,
                    $this->stringOrNull($request->query('main_group_id')),
                    $this->stringOrNull($request->query('criticality_id')),
                    $request->query('search') ?: null,
                ),
            ],
        ]);
    }

    /**
     * GET /api/pms-activities/{activity}
     */
    public function show(string $activity): JsonResponse
    {
        return response()->json(['data' => $this->activities->legacyActivityDetail($activity)]);
    }

    /**
     * POST /api/pms-activities/{activity}/mark-done
     */
    public function markDone(PmsActivityMarkDoneRequest $request, string $activity): JsonResponse
    {
        $data = $request->validated();

        $detail = $this->activities->legacyMarkDone(
            $activity,
            $data['last_done'],
            (bool) $data['unplanned'],
            $data['unplanned_description'] ?? null,
            $data['unplanned_cause'] ?? null,
            $data['remarks'] ?? null,
            $request->user()->name,
        );

        return response()->json(['data' => $detail]);
    }

    /**
     * POST /api/pms-activities/{activity}/postpone
     */
    public function postpone(PmsActivityPostponeRequest $request, string $activity): JsonResponse
    {
        $data = $request->validated();

        $detail = $this->activities->legacyPostpone(
            $activity,
            $data['postpone_date'],
            $data['description'],
            $data['possible_cause'],
            $data['remarks'] ?? null,
        );

        return response()->json(['data' => $detail]);
    }

    /**
     * GET /api/pms-activities/tickets/{ticketNo}
     */
    public function ticket(string $ticketNo): JsonResponse
    {
        return response()->json(['data' => $this->activities->legacyTicket($ticketNo)]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
