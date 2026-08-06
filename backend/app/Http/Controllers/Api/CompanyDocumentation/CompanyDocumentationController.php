<?php

namespace App\Http\Controllers\Api\CompanyDocumentation;

use App\Http\Controllers\Controller;
use App\Repositories\CompanyDocumentation\CompanyDocumentationRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Company_documentation.php. Read-only: Add/
 * Edit/Toggle Status/Delete never had a legacy write-back path built,
 * so they're not offered here — see CompanyDocumentationRepository.
 * Unlike Vessel Documentation, this module is company-wide, not
 * per-vessel — there's no vessel gate anywhere here.
 */
class CompanyDocumentationController extends Controller
{
    public function __construct(private readonly CompanyDocumentationRepository $companyDocumentation) {}

    /**
     * GET /api/company-documentation/type-options
     */
    public function typeOptions(): JsonResponse
    {
        return response()->json([
            'data' => [
                'types' => $this->companyDocumentation->legacyTypeOptions(),
            ],
        ]);
    }

    /**
     * GET /api/company-documentation/document-options
     */
    public function documentOptions(): JsonResponse
    {
        return response()->json([
            'data' => $this->companyDocumentation->legacyDocumentOptions(),
        ]);
    }

    /**
     * GET /api/company-documentation?type_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $typeId = $request->query('type_id');
        $typeId = $typeId !== null && $typeId !== '' ? (string) $typeId : null;

        $result = $this->companyDocumentation->legacyFullTable($typeId, TableQuery::fromRequest($request));

        return response()->json([
            'data' => [
                'columns' => CompanyDocumentationRepository::moduleColumns(),
                'rows' => $result['rows'],
                'meta' => $result['meta'],
            ],
        ]);
    }

    /**
     * GET /api/company-documentation/{companyDocumentationRecord}
     */
    public function show(string $companyDocumentationRecord): JsonResponse
    {
        $detail = $this->companyDocumentation->legacyDetail($companyDocumentationRecord);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
