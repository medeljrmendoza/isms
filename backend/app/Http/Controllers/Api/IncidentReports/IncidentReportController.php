<?php

namespace App\Http\Controllers\Api\IncidentReports;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncidentReports\IncidentReportRequest;
use App\Repositories\IncidentReports\IncidentReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Incident.php. Not ported: file attachment
 * upload/S3 storage, the tb_logs audit trail, and user_level-gated
 * button visibility — see IncidentReportRepository's docblocks for the
 * specific business-logic quirks that ARE kept faithfully.
 * Add/Edit/Publish/Approve/Reopen/Delete all write back to the live
 * legacy tb_incident_report table (plus tb_incident_root_cause/
 * tb_incident_person_participated on save) — see
 * IncidentReportRepository for the exact add_incident_report()/
 * publish_incident_report()/approve_incident_report()/
 * reopen_incident_report()/delete_incident_report() ports.
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
                'nature_of_incidents' => $this->incidentReports->legacyNatureOfIncidentOptions(),
                'incident_locations' => $this->incidentReports->legacyIncidentLocationOptions(),
                'incident_operations' => $this->incidentReports->legacyIncidentOperationOptions(),
                'types_of_injury' => $this->incidentReports->legacyTypeOfInjuryOptions(),
                'locations_of_injury' => $this->incidentReports->legacyLocationOfInjuryOptions(),
                'root_cause_categories' => $this->incidentReports->legacyRootCauseCategoryOptions(),
            ],
        ]);
    }

    /**
     * GET /api/incident-reports/{incidentReport} — a raw legacy incidentid string.
     */
    public function show(string $incidentReport): JsonResponse
    {
        $detail = $this->incidentReports->legacyDetail($incidentReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    /**
     * POST /api/incident-reports
     */
    public function store(IncidentReportRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $rootCauses = $validated['root_causes'] ?? [];
        $persons = $validated['persons'] ?? [];
        unset($validated['root_causes'], $validated['persons']);

        return response()->json(['data' => $this->incidentReports->legacySave(null, $validated, $rootCauses, $persons)], 201);
    }

    /**
     * PUT /api/incident-reports/{incidentReport}
     */
    public function update(IncidentReportRequest $request, string $incidentReport): JsonResponse
    {
        $validated = $request->validated();
        $rootCauses = $validated['root_causes'] ?? [];
        $persons = $validated['persons'] ?? [];
        unset($validated['root_causes'], $validated['persons']);

        return response()->json(['data' => $this->incidentReports->legacySave($incidentReport, $validated, $rootCauses, $persons)]);
    }

    /**
     * DELETE /api/incident-reports/{incidentReport}
     */
    public function destroy(string $incidentReport): JsonResponse
    {
        $this->incidentReports->legacyDelete($incidentReport);

        return response()->json(['data' => ['ok' => true]]);
    }

    /**
     * POST /api/incident-reports/{incidentReport}/publish
     */
    public function publish(string $incidentReport): JsonResponse
    {
        return response()->json(['data' => $this->incidentReports->legacyPublish($incidentReport)]);
    }

    /**
     * POST /api/incident-reports/{incidentReport}/approve
     */
    public function approve(string $incidentReport): JsonResponse
    {
        return response()->json(['data' => $this->incidentReports->legacyApprove($incidentReport)]);
    }

    /**
     * POST /api/incident-reports/{incidentReport}/reopen
     */
    public function reopen(string $incidentReport): JsonResponse
    {
        return response()->json(['data' => $this->incidentReports->legacyReopen($incidentReport)]);
    }
}
