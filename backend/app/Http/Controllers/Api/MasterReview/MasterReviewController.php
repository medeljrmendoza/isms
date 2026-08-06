<?php

namespace App\Http\Controllers\Api\MasterReview;

use App\Http\Controllers\Controller;
use App\Repositories\MasterReview\MasterReviewRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Master_review.php. Read-only: Add/Edit/
 * Approve/Disapprove/Disregard/Recommend Approval/Under Review/Reopen/
 * Delete never had a legacy write-back path built, so they're not
 * offered here — see MasterReviewRepository.
 */
class MasterReviewController extends Controller
{
    public function __construct(private readonly MasterReviewRepository $masterReviews) {}

    /**
     * GET /api/master-review/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->masterReviews->legacyVesselOptions($request->user()?->legacy_user_id),
                'chapters' => $this->masterReviews->legacyChapterOptions(),
            ],
        ]);
    }

    /**
     * GET /api/master-review/document-options?chapter_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->masterReviews->legacyDocumentOptionsForChapter((string) $request->query('chapter_id')),
        ]);
    }

    /**
     * GET /api/master-review?vessel_id=&start_quarter=&start_year=&end_quarter=&end_year=&record_status=&chapter_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->masterReviews->legacyFullTable(
            $this->stringOrNull($request->query('vessel_id')),
            $this->intOrNull($request->query('start_quarter')),
            $this->intOrNull($request->query('start_year')),
            $this->intOrNull($request->query('end_quarter')),
            $this->intOrNull($request->query('end_year')),
            $request->query('record_status') ?: null,
            $this->stringOrNull($request->query('chapter_id')),
            TableQuery::fromRequest($request),
            $request->user()?->legacy_user_id,
        );

        return response()->json(['data' => ['columns' => MasterReviewRepository::moduleColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/master-review/{masterReview}
     */
    public function show(string $masterReview): JsonResponse
    {
        $detail = $this->masterReviews->legacyDetail($masterReview);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
