<?php

namespace App\Http\Controllers\Api\IncidentReports;

use App\Http\Controllers\Controller;
use App\Repositories\IncidentReports\IncidentReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Incident.php. Read-only: Add/Edit/Publish/
 * Approve/Delete/Reopen never had a legacy write-back path built, so
 * they're not offered here — see IncidentReportRepository.
 */
class IncidentReportController extends Controller
{
    public function __construct(private readonly IncidentReportRepository $incidentReports) {}

    /**
     * GET /api/incident-reports
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $year = $request->query('year');

        $result = $this->incidentReports->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId !== '' ? $vesselId : null,
            $year !== '' ? $year : null,
            $request->user()?->legacy_user_id,
        );

        return response()->json(['data' => ['columns' => IncidentReportRepository::moduleColumns(), ...$result]]);
    }

    /**
     * GET /api/incident-reports/options
     */
    public function options(Request $request): JsonResponse
    {
        $legacyUserId = $request->user()?->legacy_user_id;
        $years = $this->incidentReports->legacyYears($legacyUserId);
        if (! in_array((int) date('Y'), $years, true)) {
            array_unshift($years, (int) date('Y'));
        }

        return response()->json([
            'data' => [
                'vessels' => $this->incidentReports->legacyVesselOptions($legacyUserId),
                'years' => array_map(fn ($y) => (string) $y, $years),
            ],
        ]);
    }

    /**
     * GET /api/incident-reports/{incidentReport} — a legacy incidentid string.
     */
    public function show(string $incidentReport): JsonResponse
    {
        $detail = $this->incidentReports->legacyDetail($incidentReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
