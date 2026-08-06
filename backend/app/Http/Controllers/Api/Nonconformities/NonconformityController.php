<?php

namespace App\Http\Controllers\Api\Nonconformities;

use App\Http\Controllers\Controller;
use App\Repositories\Nonconformities\NonconformityRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Nonconformities.php. Read-only: this module's
 * Add/Edit/Publish/Approve/Delete/Reopen actions never had a legacy
 * write-back path built (unlike PMS Department/Classifications/Defects,
 * which do) — they only ever wrote to the local database. With no local
 * database, those actions are disabled on the frontend rather than left
 * to hard-fail; only the read paths (list/detail/options) remain here.
 */
class NonconformityController extends Controller
{
    public function __construct(private readonly NonconformityRepository $nonconformities) {}

    /**
     * GET /api/nonconformities
     */
    public function index(Request $request): JsonResponse
    {
        $vesselOrCompany = $request->query('vessel_company');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $result = $this->nonconformities->legacyFullTable(
            TableQuery::fromRequest($request),
            $vesselOrCompany !== '' ? $vesselOrCompany : null,
            $dateFrom !== '' ? $dateFrom : null,
            $dateTo !== '' ? $dateTo : null,
            $request->user()?->legacy_user_id,
        );

        return response()->json(['data' => ['columns' => NonconformityRepository::moduleColumns(), ...$result]]);
    }

    /**
     * GET /api/nonconformities/options — vessel list for the filter bar.
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->nonconformities->legacyVesselOptions($request->user()?->legacy_user_id),
            ],
        ]);
    }

    /**
     * GET /api/nonconformities/{nonconformity} — {nonconformity} is the
     * raw legacy ncID string (see legacyFullTable()'s row `id`).
     */
    public function show(string $nonconformity): JsonResponse
    {
        $detail = $this->nonconformities->legacyDetail($nonconformity);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }
}
