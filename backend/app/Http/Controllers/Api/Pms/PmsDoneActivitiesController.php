<?php

namespace App\Http\Controllers\Api\Pms;

use App\Http\Controllers\Controller;
use App\Repositories\Pms\PmsDoneActivitiesRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Ported from Controllers/Pms_done_activities.php. Read-only report — no add/edit/delete anywhere in the legacy view. */
class PmsDoneActivitiesController extends Controller
{
    public function __construct(private readonly PmsDoneActivitiesRepository $doneActivities) {}

    /**
     * GET /api/pms-done-activities/options
     */
    public function options(): JsonResponse
    {
        return response()->json(['data' => ['vessels' => $this->doneActivities->legacyVesselOptions()]]);
    }

    /**
     * GET /api/pms-done-activities?vessel_id=&date_from=&date_to=&...
     */
    public function index(Request $request): JsonResponse
    {
        $dateFrom = $request->query('date_from') ?: null;
        $dateTo = $request->query('date_to') ?: null;

        if (! $request->query('vessel_id') || ! $dateFrom || ! $dateTo) {
            throw ValidationException::withMessages([
                'vessel_id' => ['Vessel, Date From, and Date To are all required.'],
            ]);
        }

        $result = $this->doneActivities->legacyTable((string) $request->query('vessel_id'), $dateFrom, $dateTo, TableQuery::fromRequest($request));

        return response()->json(['data' => $result]);
    }
}
