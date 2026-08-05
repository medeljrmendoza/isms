<?php

namespace App\Http\Controllers\Api\MasterReview;

use App\Http\Controllers\Controller;
use App\Http\Requests\MasterReview\MasterReviewRequest;
use App\Models\MasterReview\MasterReview;
use App\Repositories\MasterReview\MasterReviewRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Ported from Controllers/Master_review.php. Not ported: the tb_logs
 * audit trail, user_level-gated button visibility (MEMBER-only
 * restrictions — every action is available here), the printable
 * header/footer (tb_report_footer, Setup-managed report branding not
 * migrated anywhere else either), and the S3-file-sync side effects
 * bundled into every action (delete+reinsert purely to poke a
 * vessel-side sync watcher — no equivalent system exists here). See
 * MasterReviewRepository's docblocks for the schema-level deferrals.
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
                ...(LegacyDb::isConfigured() ? [
                    'vessels' => $this->masterReviews->legacyVesselOptions($request->user()?->legacy_user_id),
                    'chapters' => $this->masterReviews->legacyChapterOptions(),
                ] : [
                    'vessels' => $this->masterReviews->vesselOptions(),
                    'chapters' => $this->masterReviews->chapterOptions(),
                ]),
                // A new record's manual_chapter_id/manual_document_id are
                // local ManualChapter/ManualDocument foreign keys — legacy
                // chapter/document ids don't have a matching local row, so
                // creation is only offered when reading locally.
                'can_create_record' => ! LegacyDb::isConfigured(),
            ],
        ]);
    }

    /**
     * GET /api/master-review/document-options?chapter_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json([
                'data' => $this->masterReviews->legacyDocumentOptionsForChapter((string) $request->query('chapter_id')),
            ]);
        }

        return response()->json([
            'data' => $this->masterReviews->documentOptionsForChapter((int) $request->query('chapter_id')),
        ]);
    }

    /**
     * GET /api/master-review?vessel_id=&start_quarter=&start_year=&end_quarter=&end_year=&record_status=&chapter_id=&...
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
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

        $paginator = $this->masterReviews->fullTable(
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
                'columns' => MasterReviewRepository::moduleColumns(),
                'rows' => collect($paginator->items())->map(fn (MasterReview $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/master-review/{masterReview}
     */
    public function show(string $masterReview): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->masterReviews->legacyDetail($masterReview);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = MasterReview::query()->with(['vessel', 'manualChapter', 'manualDocument', 'present'])->findOrFail((int) $masterReview);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/master-review
     */
    public function store(MasterReviewRequest $request): JsonResponse
    {
        [$data, $present] = $this->splitPayload($request->validated());

        $review = $this->masterReviews->create($data, $present);
        $review->load(['vessel', 'manualChapter', 'manualDocument', 'present']);

        return response()->json(['data' => $this->mapDetail($review)], 201);
    }

    /**
     * PUT /api/master-review/{masterReview}
     */
    public function update(MasterReviewRequest $request, MasterReview $masterReview): JsonResponse
    {
        [$data, $present] = $this->splitPayload($request->validated());

        $review = $this->masterReviews->update($masterReview, $data, $present);
        $review->load(['vessel', 'manualChapter', 'manualDocument', 'present']);

        return response()->json(['data' => $this->mapDetail($review)]);
    }

    /**
     * POST /api/master-review/{masterReview}/approve
     */
    public function approve(MasterReview $masterReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->masterReviews->approve($masterReview))]);
    }

    /**
     * POST /api/master-review/{masterReview}/disapprove
     */
    public function disapprove(MasterReview $masterReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->masterReviews->disapprove($masterReview))]);
    }

    /**
     * POST /api/master-review/{masterReview}/disregard
     */
    public function disregard(MasterReview $masterReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->masterReviews->disregard($masterReview))]);
    }

    /**
     * POST /api/master-review/{masterReview}/recommend-approval
     */
    public function recommendApproval(MasterReview $masterReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->masterReviews->recommendApproval($masterReview))]);
    }

    /**
     * POST /api/master-review/{masterReview}/under-review
     */
    public function underReview(MasterReview $masterReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->masterReviews->underReview($masterReview))]);
    }

    /**
     * POST /api/master-review/{masterReview}/reopen
     */
    public function reopen(MasterReview $masterReview): JsonResponse
    {
        return response()->json(['data' => $this->mapDetail($this->masterReviews->reopen($masterReview))]);
    }

    /**
     * DELETE /api/master-review/{masterReview}
     */
    public function destroy(MasterReview $masterReview): JsonResponse
    {
        $this->masterReviews->delete($masterReview);

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

    private function sms(MasterReview $r): string
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

    private function mapRow(MasterReview $r): array
    {
        $isOpen = $r->shore_status === '';

        return [
            'id' => $r->id,
            'vessel' => $r->vessel?->display_name ?? '',
            'review_date' => $r->review_date->format('Y-m-d'),
            'added_by' => $r->added_by,
            // Stored as "Q1".."Q4" (see MasterReviewRepository::create()) —
            // the API deals in the plain integer throughout.
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
            'can_under_review' => $isOpen,
            'can_disapprove' => $isOpen,
            'can_disregard' => $isOpen,
            'can_delete' => $isOpen && $r->added_by === 'SHORE',
            'can_reopen' => ! $isOpen,
        ];
    }

    private function mapDetail(MasterReview $r): array
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
