<?php

namespace App\Http\Controllers\Api\IspsReview;

use App\Http\Controllers\Controller;
use App\Http\Requests\IspsReview\IspsReviewRequest;
use App\Models\IspsReview\IspsReview;
use App\Repositories\IspsReview\IspsReviewRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Isps_review.php. Not ported: the tb_logs
 * audit trail, user_level-gated button visibility (MEMBER-only
 * restrictions — every action is available here), the printable
 * header/footer, and the S3-file-sync side effects bundled into every
 * action. See IspsReviewRepository's docblocks for schema-level
 * deferrals — this module mirrors MasterReview's shape exactly.
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
                ...(LegacyDb::isConfigured() ? [
                    'vessels' => $this->ispsReviews->legacyVesselOptions($request->user()?->legacy_user_id),
                    'chapters' => $this->ispsReviews->legacyChapterOptions(),
                ] : [
                    'vessels' => $this->ispsReviews->vesselOptions(),
                    'chapters' => $this->ispsReviews->chapterOptions(),
                ]),
                'can_create_record' => ! LegacyDb::isConfigured(),
            ],
        ]);
    }

    /**
     * GET /api/isps-review/document-options?chapter_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json([
                'data' => $this->ispsReviews->legacyDocumentOptionsForChapter((string) $request->query('chapter_id')),
            ]);
        }

        return response()->json([
            'data' => $this->ispsReviews->documentOptionsForChapter((int) $request->query('chapter_id')),
        ]);
    }

    /**
     * GET /api/isps-review?vessel_id=&start_quarter=&start_year=&end_quarter=&end_year=&record_status=&chapter_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
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

        $paginator = $this->ispsReviews->fullTable(
            $this->intOrNull($request->query('vessel_id')),
            $this->intOrNull($request->query('start_quarter')),
            $this->intOrNull($request->query('start_year')),
            $this->intOrNull($request->query('end_quarter')),
            $this->intOrNull($request->query('end_year')),
            $request->query('record_status') ?: null,
            $this->intOrNull($request->query('chapter_id')),
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => IspsReviewRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (IspsReview $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/isps-review/{ispsReview}
     */
    public function show(string $ispsReview): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->ispsReviews->legacyDetail($ispsReview);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = IspsReview::query()->with(['vessel', 'manualChapter', 'manualDocument', 'present'])->findOrFail((int) $ispsReview);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/isps-review
     */
    public function store(IspsReviewRequest $request): JsonResponse
    {
        [$data, $present] = $this->splitPayload($request->validated());

        $review = $this->ispsReviews->create($data, $present);
        $review->load(['vessel', 'manualChapter', 'manualDocument', 'present']);

        return response()->json(['data' => $this->mapDetail($review)], 201);
    }

    /**
     * PUT /api/isps-review/{ispsReview}
     */
    public function update(IspsReviewRequest $request, IspsReview $ispsReview): JsonResponse
    {
        [$data, $present] = $this->splitPayload($request->validated());

        $review = $this->ispsReviews->update($ispsReview, $data, $present);
        $review->load(['vessel', 'manualChapter', 'manualDocument', 'present']);

        return response()->json(['data' => $this->mapDetail($review)]);
    }

    /**
     * POST /api/isps-review/{ispsReview}/approve
     */
    public function approve(IspsReview $ispsReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->ispsReviews->approve($ispsReview))]);
    }

    /**
     * POST /api/isps-review/{ispsReview}/disapprove
     */
    public function disapprove(IspsReview $ispsReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->ispsReviews->disapprove($ispsReview))]);
    }

    /**
     * POST /api/isps-review/{ispsReview}/disregard
     */
    public function disregard(IspsReview $ispsReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->ispsReviews->disregard($ispsReview))]);
    }

    /**
     * POST /api/isps-review/{ispsReview}/recommend-approval
     */
    public function recommendApproval(IspsReview $ispsReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->ispsReviews->recommendApproval($ispsReview))]);
    }

    /**
     * POST /api/isps-review/{ispsReview}/reopen
     */
    public function reopen(IspsReview $ispsReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->ispsReviews->reopen($ispsReview))]);
    }

    /**
     * DELETE /api/isps-review/{ispsReview}
     */
    public function destroy(IspsReview $ispsReview): JsonResponse
    {
        $this->ispsReviews->delete($ispsReview);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    /** @return array{0: array, 1: array} */
    private function splitPayload(array $validated): array
    {
        $present = $validated['present'] ?? [];
        unset($validated['present']);

        return [$validated, $present];
    }

    private function sms(IspsReview $r): string
    {
        $chapterRef = $r->manualDocument?->manualChapter?->reference_no ?? $r->manualChapter?->reference_no ?? '';
        $docRef = $r->manualDocument?->reference_no;
        $parts = array_filter([$chapterRef, $docRef]);
        $label = implode(' / ', $parts);

        if ($r->manual_section) {
            $label .= " ({$r->manual_section})";
        }

        return trim($label);
    }

    private function mapRow(IspsReview $r): array
    {
        $isOpen = $r->shore_status === '';

        return [
            'id' => $r->id,
            'vessel' => $r->vessel?->display_name ?? '',
            'review_date' => $r->review_date->format('Y-m-d'),
            'added_by' => $r->added_by,
            // Stored as "Q1".."Q4" (see IspsReviewRepository::create()).
            'review_quarter' => (int) ltrim((string) $r->review_quarter, 'Q'),
            'review_year' => $r->review_year,
            'sms' => $this->sms($r),
            'review_recommendation' => $r->review_recommendation,
            'has_vessel_remarks' => filled($r->vessel_remarks),
            'has_shore_remarks' => filled($r->shore_remarks),
            'shore_status' => $r->shore_status,
            'can_edit' => $isOpen,
            'can_approve' => $isOpen,
            'can_recommend_approval' => $isOpen,
            'can_disapprove' => $isOpen,
            'can_disregard' => $isOpen,
            'can_delete' => $isOpen && $r->added_by === 'SHORE',
            'can_reopen' => ! $isOpen,
        ];
    }

    private function mapDetail(IspsReview $r): array
    {
        return [
            ...$this->mapRow($r),
            'manual_chapter_id' => $r->manual_chapter_id,
            'manual_document_id' => $r->manual_document_id,
            'manual_section' => $r->manual_section,
            'review_description' => $r->review_description,
            'shore_reviewed_by' => $r->shore_reviewed_by,
            'shore_remarks' => $r->shore_remarks,
            'vessel_reviewed_by' => $r->vessel_reviewed_by,
            'vessel_reviewed_position' => $r->vessel_reviewed_position,
            'vessel_remarks' => $r->vessel_remarks,
            'present' => $r->present->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'position' => $p->position,
            ])->all(),
        ];
    }

    private function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
