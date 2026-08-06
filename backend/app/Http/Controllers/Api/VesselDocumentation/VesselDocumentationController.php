<?php

namespace App\Http\Controllers\Api\VesselDocumentation;

use App\Http\Controllers\Controller;
use App\Repositories\VesselDocumentation\VesselDocumentationRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Vessel_documentation.php. Read-only: Add/
 * Edit/Toggle Status/Delete never had a legacy write-back path built,
 * so they're not offered here — see VesselDocumentationRepository.
 */
class VesselDocumentationController extends Controller
{
    public function __construct(private readonly VesselDocumentationRepository $vesselDocumentation) {}

    /**
     * GET /api/vessel-documentation/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->vesselDocumentation->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/vessel-documentation/type-options?vessel_id=
     */
    public function typeOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->vesselDocumentation->legacyDocumentTypeOptionsForVessel((string) $request->query('vessel_id')),
        ]);
    }

    /**
     * GET /api/vessel-documentation/document-options?vessel_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->vesselDocumentation->legacyDocumentOptionsForVessel((string) $request->query('vessel_id')),
        ]);
    }

    /**
     * GET /api/vessel-documentation?vessel_id=&type_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $typeId = $request->query('type_id');
        $typeId = $typeId !== null && $typeId !== '' ? (string) $typeId : null;

        $result = $this->vesselDocumentation->legacyFullTable(
            (string) $request->query('vessel_id'),
            $typeId,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => VesselDocumentationRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/vessel-documentation/{vesselDocumentRecord}
     */
    public function show(string $vesselDocumentRecord): JsonResponse
    {
        $detail = $this->vesselDocumentation->legacyDetail($vesselDocumentRecord);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
