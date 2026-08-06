<?php

namespace App\Http\Controllers\Api\IspsReview;

use App\Http\Controllers\Controller;
use App\Repositories\IspsReview\IspsReviewRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Isps_review.php. Read-only: Add/Edit/
 * Approve/Disapprove/Disregard/Recommend Approval/Reopen/Delete never
 * had a legacy write-back path built, so they're not offered here —
 * see IspsReviewRepository. Mirrors MasterReview's shape exactly.
 */
class IspsReviewController extends Controller
{
    public function __construct(private readonly IspsReviewRepository $ispsReviews) {}

    /**
     * GET /api/isps-review/options
     */
    public function options(Request $request): JsonResponse
    {
        return response()->json([
            'data' => [
                'vessels' => $this->ispsReviews->legacyVesselOptions($request->user()?->legacy_user_id),
                'chapters' => $this->ispsReviews->legacyChapterOptions(),
            ],
        ]);
    }

    /**
     * GET /api/isps-review/document-options?chapter_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->ispsReviews->legacyDocumentOptionsForChapter((string) $request->query('chapter_id')),
        ]);
    }

    /**
     * GET /api/isps-review?vessel_id=&start_quarter=&start_year=&end_quarter=&end_year=&record_status=&chapter_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->ispsReviews->legacyFullTable(
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

        return response()->json(['data' => ['columns' => IspsReviewRepository::moduleColumns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/isps-review/{ispsReview}
     */
    public function show(string $ispsReview): JsonResponse
    {
        $detail = $this->ispsReviews->legacyDetail($ispsReview);
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
