<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pms\PmsRunningHoursUpdateRequest;
use App\Repositories\Pms\PmsRunningHoursRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Ported from Controllers/Pms_running_hours_equipments.php — see PmsRunningHoursRepository's docblocks for what's dropped/simplified. */
class PmsRunningHoursController extends Controller
{
    public function __construct(private readonly PmsRunningHoursRepository $runningHours) {}

    /**
     * GET /api/pms-running-hours/options
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => ['vessels' => $this->runningHours->vesselOptions()]]);
    }

    /**
     * GET /api/pms-running-hours?vessel_id=&month=&year=
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = (int) $request->query('vessel_id');
        $month = $this->intOrNull($request->query('month'));
        $year = $this->intOrNull($request->query('year'));

        return response()->json([
            'data' => [
                'current_period' => $this->runningHours->currentPeriod($vesselId),
                'period_options' => $this->runningHours->periodOptions($vesselId),
                'rows' => $this->runningHours->table($vesselId, $month, $year),
            ],
        ]);
    }

    /**
     * POST /api/pms-running-hours/update
     */
    public function update(PmsRunningHoursUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->runningHours->updateRunningHours($data['equipment_id'], $data['date'], (float) $data['hours'], $data['remarks'] ?? null);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/pms-running-hours/proceed-next-month
     */
    public function proceedNextMonth(Request $request): JsonResponse
    {
        $this->runningHours->proceedNextMonth((int) $request->input('vessel_id'));

        return response()->json(['data' => ['ok' => true]]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
