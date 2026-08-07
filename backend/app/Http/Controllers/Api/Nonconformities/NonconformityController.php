<?php

namespace App\Http\Controllers\Api\Nonconformities;

use App\Http\Controllers\Controller;
use App\Http\Requests\Nonconformities\NonconformityRequest;
use App\Repositories\Nonconformities\NonconformityRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Nonconformities.php. Not ported: the tb_logs
 * audit trail, user_level-gated button visibility (dropped per the
 * same no-roles-yet precedent as Defects/PMS Department/ExposureHours),
 * S3 file attachments/print-header sync (no infra anywhere in this
 * migration), and the printable header/footer (tb_report_footer).
 * Add/Edit/Inactivate/Publish/Approve/Reopen/Delete all write back to
 * the live legacy tb_nonconformities table — see NonconformityRepository
 * for the exact save_item()/edit_stat()/publish_nonconformity()/
 * approve_nonconformity()/reopen_nonconformity()/delete_nonconformity()
 * ports.
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
                'chapters' => $this->nonconformities->legacyChapterOptions(),
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

    /**
     * POST /api/nonconformities
     */
    public function store(NonconformityRequest $request): JsonResponse
    {
        return response()->json(['data' => $this->nonconformities->legacySave(null, $request->validated())], 201);
    }

    /**
     * PUT /api/nonconformities/{nonconformity}
     */
    public function update(NonconformityRequest $request, string $nonconformity): JsonResponse
    {
        return response()->json(['data' => $this->nonconformities->legacySave($nonconformity, $request->validated())]);
    }

    /**
     * POST /api/nonconformities/{nonconformity}/toggle-inactive
     */
    public function toggleInactive(string $nonconformity): JsonResponse
    {
        return response()->json(['data' => $this->nonconformities->legacyToggleInactive($nonconformity)]);
    }

    /**
     * POST /api/nonconformities/{nonconformity}/toggle-publish
     */
    public function togglePublish(string $nonconformity): JsonResponse
    {
        return response()->json(['data' => $this->nonconformities->legacyTogglePublish($nonconformity)]);
    }

    /**
     * POST /api/nonconformities/{nonconformity}/approve
     */
    public function approve(string $nonconformity): JsonResponse
    {
        return response()->json(['data' => $this->nonconformities->legacyApprove($nonconformity)]);
    }

    /**
     * POST /api/nonconformities/{nonconformity}/reopen
     */
    public function reopen(string $nonconformity): JsonResponse
    {
        return response()->json(['data' => $this->nonconformities->legacyReopen($nonconformity)]);
    }

    /**
     * DELETE /api/nonconformities/{nonconformity} — soft-delete (is_inactive), matching legacy.
     */
    public function destroy(string $nonconformity): JsonResponse
    {
        return response()->json(['data' => $this->nonconformities->legacyDelete($nonconformity)]);
    }
}
