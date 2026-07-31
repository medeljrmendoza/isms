<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pms\PmsActivityMarkDoneRequest;
use App\Http\Requests\Pms\PmsActivityPostponeRequest;
use App\Models\Pms\PmsActivity;
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
                'vessels' => $this->activities->vesselOptions(),
                'departments' => $this->activities->departmentOptions(),
                'criticalities' => $this->activities->criticalityOptions(),
                'main_groups' => $this->activities->mainGroupOptions(),
            ],
        ]);
    }

    /**
     * GET /api/pms-activities?vessel_id=&year=&department_id=&criticality_id=&main_group_id=&search=
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = (int) $request->query('vessel_id');
        $year = $this->intOrNull($request->query('year'));

        return response()->json([
            'data' => [
                'current_period' => $this->activities->currentPeriod($vesselId),
                'year_options' => array_values(array_unique(array_filter([
                    $this->activities->currentYear($vesselId),
                    ...$this->activities->historicalYears($vesselId),
                ]))),
                'rows' => $this->activities->table(
                    $vesselId,
                    $year,
                    $this->intOrNull($request->query('main_group_id')),
                    $this->intOrNull($request->query('criticality_id')),
                    $request->query('search') ?: null,
                ),
            ],
        ]);
    }

    /**
     * GET /api/pms-activities/{activity}
     */
    public function show(PmsActivity $activity): JsonResponse
    {
        return response()->json(['data' => $this->activities->activityDetail($activity)]);
    }

    /**
     * POST /api/pms-activities/{activity}/mark-done
     */
    public function markDone(PmsActivityMarkDoneRequest $request, PmsActivity $activity): JsonResponse
    {
        $data = $request->validated();

        $activity = $this->activities->markDone(
            $activity,
            $data['last_done'],
            (bool) $data['unplanned'],
            $data['unplanned_description'] ?? null,
            $data['unplanned_cause'] ?? null,
            $data['remarks'] ?? null,
            $request->user()->name,
        );

        return response()->json(['data' => $this->activities->activityDetail($activity)]);
    }

    /**
     * POST /api/pms-activities/{activity}/postpone
     */
    public function postpone(PmsActivityPostponeRequest $request, PmsActivity $activity): JsonResponse
    {
        $data = $request->validated();

        $activity = $this->activities->postpone(
            $activity,
            $data['postpone_date'],
            $data['description'],
            $data['possible_cause'],
            $data['remarks'] ?? null,
        );

        return response()->json(['data' => $this->activities->activityDetail($activity)]);
    }

    /**
     * GET /api/pms-activities/tickets/{ticketNo}
     */
    public function ticket(string $ticketNo): JsonResponse
    {
        $ticket = $this->activities->ticket($ticketNo);

        return response()->json([
            'data' => [
                'ticket_no' => $ticket->ticket_no,
                'type' => $ticket->type,
                'vessel' => $ticket->vessel->display_name,
                'activity_name' => $ticket->activity_name,
                'date_of_activity' => $ticket->date_of_activity->format('Y-m-d'),
                'description' => $ticket->description,
                'possible_cause' => $ticket->possible_cause,
                'remarks' => $ticket->remarks,
                'incharge' => $ticket->incharge,
                'frequency' => $ticket->unit === 'O'
                    ? $ticket->other_unit
                    : ($ticket->min_count_interval !== 0
                        ? "{$ticket->min_count_interval} - {$ticket->max_count_interval} {$ticket->unit}"
                        : "{$ticket->max_count_interval} {$ticket->unit}"),
                'is_overdue' => $ticket->is_overdue,
                'equipment_name' => $ticket->equipment_name,
                'part_name' => $ticket->part_name,
                'previous_last_done' => $ticket->previous_last_done?->format('Y-m-d'),
                'previous_due_date' => $ticket->previous_due_date?->format('Y-m-d'),
                'reported_by' => $ticket->reported_by,
                'created_at' => $ticket->created_at->format('Y-m-d H:i'),
            ],
        ]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
