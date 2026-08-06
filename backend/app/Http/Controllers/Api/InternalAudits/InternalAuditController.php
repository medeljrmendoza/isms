<?php

namespace App\Http\Controllers\Api\InternalAudits;

use App\Http\Controllers\Controller;
use App\Repositories\InternalAudits\InternalAuditReportRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Internal.php. Read-only: Add/Edit/Delete
 * never had a legacy write-back path built, so they're not offered
 * here — see InternalAuditReportRepository.
 */
class InternalAuditController extends Controller
{
    public function __construct(private readonly InternalAuditReportRepository $internalAudits) {}

    /**
     * GET /api/internal-audits
     */
    public function index(Request $request): JsonResponse
    {
        $vesselId = $request->query('vessel_id');
        $vesselId = $vesselId !== '' ? $vesselId : null;

        $result = $this->internalAudits->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselId,
            $request->user()?->legacy_user_id,
        );

        return response()->json([
            'data' => [
                'columns' => InternalAuditReportRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/internal-audits/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->internalAudits->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/internal-audits/{internalAuditReport}
     */
    public function show(string $internalAuditReport): JsonResponse
    {
        $detail = $this->internalAudits->legacyDetail($internalAuditReport);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
