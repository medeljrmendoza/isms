<?php

namespace App\Http\Controllers\Api\ExternalAudits;

use App\Http\Controllers\Controller;
use App\Repositories\ExternalAudits\ExternalAuditReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/External.php. Read-only: Add/Edit/Publish/
 * Approve/Delete never had a legacy write-back path built, so they're
 * not offered here — see ExternalAuditReportRepository.
 */
class ExternalAuditController extends Controller
{
    public function __construct(private readonly ExternalAuditReportRepository $externalAudits) {}

    /**
     * GET /api/external-audits
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->externalAudits->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => ExternalAuditReportRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/external-audits/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->externalAudits->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/external-audits/{externalAuditReport}
     */
    public function show(string $externalAuditReport): JsonResponse
    {
        $detail = $this->externalAudits->legacyDetail($externalAuditReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
