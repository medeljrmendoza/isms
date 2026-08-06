<?php

namespace App\Http\Controllers\Api\CompanyInspections;

use App\Http\Controllers\Controller;
use App\Repositories\CompanyInspections\AuditReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Company.php. Read-only: Add/Edit/Delete never
 * had a legacy write-back path built, so they're not offered here — see
 * AuditReportRepository.
 */
class CompanyInspectionController extends Controller
{
    public function __construct(private readonly AuditReportRepository $auditReports) {}

    /**
     * GET /api/company-inspections
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->auditReports->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => AuditReportRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/company-inspections/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->auditReports->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/company-inspections/{auditReport}
     */
    public function show(string $auditReport): JsonResponse
    {
        $detail = $this->auditReports->legacyDetail($auditReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
