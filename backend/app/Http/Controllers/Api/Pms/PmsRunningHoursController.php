<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pms\PmsRunningHoursUpdateRequest;
use App\Repositories\Pms\PmsRunningHoursRepository;
use App\Support\LegacyDb;
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
        $vessels = LegacyDb::isConfigured() ? $this->runningHours->legacyVesselOptions() : $this->runningHours->vesselOptions();

        return response()->json(['data' => ['vessels' => $vessels]]);
    }

    /**
     * GET /api/pms-running-hours?vessel_id=&month=&year=
     */
    public function index(Request $request): JsonResponse
    {
        $month = $this->intOrNull($request->query('month'));
        $year = $this->intOrNull($request->query('year'));

        if (LegacyDb::isConfigured()) {
            $vesselId = (string) $request->query('vessel_id');

            return response()->json([
                'data' => [
                    'current_period' => $this->runningHours->legacyCurrentPeriod($vesselId),
                    'period_options' => $this->runningHours->legacyPeriodOptions($vesselId),
                    'rows' => $this->runningHours->legacyTable($vesselId, $month, $year),
                ],
            ]);
        }

        $vesselId = (int) $request->query('vessel_id');

        return response()->json([
            'data' => [
                'current_period' => $this->runningHours->currentPeriod($vesselId),
                'period_options' => $this->runningHours->periodOptions($vesselId),
                'rows' => $this->runningHours->table($vesselId, $month, $year),
            ],
        ]);
    }

    /**
     * GET /api/pms-running-hours/parts?vessel_id=&equipment_id=&month=&year=
     *
     * Legacy-only, matching the local Eloquent port's documented scope
     * (parts are never given their own drill-down page there).
     */
    public function parts(Request $request): JsonResponse
    {
        abort_unless(LegacyDb::isConfigured(), 404);

        $month = $this->intOrNull($request->query('month'));
        $year = $this->intOrNull($request->query('year'));

        $result = $this->runningHours->legacyPartsTable(
            (string) $request->query('vessel_id'),
            (string) $request->query('equipment_id'),
            $month,
            $year,
        );

        return response()->json([
            'data' => [
                'current_period' => $this->runningHours->legacyCurrentPeriod((string) $request->query('vessel_id')),
                'equipment_code' => $result['equipment_code'],
                'equipment_name' => $result['equipment_name'],
                'rows' => $result['rows'],
            ],
        ]);
    }

    /**
     * POST /api/pms-running-hours/update
     */
    public function update(PmsRunningHoursUpdateRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (LegacyDb::isConfigured()) {
            $this->runningHours->legacyUpdateRunningHours((string) $data['equipment_id'], $data['date'], (float) $data['hours'], $data['remarks'] ?? null);

            return response()->json(['data' => ['ok' => true]]);
        }

        $this->runningHours->updateRunningHours($data['equipment_id'], $data['date'], (float) $data['hours'], $data['remarks'] ?? null);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/pms-running-hours/proceed-next-month
     */
    public function proceedNextMonth(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $this->runningHours->legacyProceedNextMonth((string) $request->input('vessel_id'));

            return response()->json(['data' => ['ok' => true]]);
        }

        $this->runningHours->proceedNextMonth((int) $request->input('vessel_id'));

        return response()->json(['data' => ['ok' => true]]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
