<?php

namespace App\Http\Controllers\Api\RevisionHistory;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevisionHistory\RevisionHistoryRequest;
use App\Models\RevisionHistory\ManualRevision;
use App\Repositories\RevisionHistory\RevisionHistoryRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Ported from Controllers/Sms_revision.php. Not ported: the tb_logs
 * audit trail, user_level-gated button visibility (MEMBER-only
 * restrictions — every action is available here), the printable
 * header/footer (tb_report_footer, Setup-managed report branding not
 * migrated anywhere else either), and the S3-file-sync side effects
 * bundled into every write (delete+reinsert purely to poke a
 * vessel-side sync watcher — no equivalent system exists here). The
 * legacy Add form is only reachable after first picking a Manual on the
 * list page (its chapterID comes from the URL); here the chapter and
 * procedure selects live directly in the Add/Edit form instead.
 */
class RevisionHistoryController extends Controller
{
    public function __construct(private readonly RevisionHistoryRepository $revisions) {}

    /**
     * GET /api/revision-history/options
     */
    public function options(): JsonResponse
    {
        return response()->json([
            'data' => [
                'chapters' => LegacyDb::isConfigured() ? $this->revisions->legacyChapterOptions() : $this->revisions->chapterOptions(),
                // A new record's manual_document_id is a local
                // ManualDocument foreign key — legacy document ids don't
                // have a matching local row, so creation is only offered
                // when reading locally.
                'can_create_record' => ! LegacyDb::isConfigured(),
            ],
        ]);
    }

    /**
     * GET /api/revision-history/document-options?chapter_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            return response()->json([
                'data' => $this->revisions->legacyDocumentOptionsForChapter((string) $request->query('chapter_id')),
            ]);
        }

        return response()->json([
            'data' => $this->revisions->documentOptionsForChapter((int) $request->query('chapter_id')),
        ]);
    }

    /**
     * GET /api/revision-history?chapter_id=&date_from=&date_to=&...
     */
    public function index(Request $request): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $result = $this->revisions->legacyFullTable(
                $this->stringOrNull($request->query('chapter_id')),
                $request->query('date_from') ?: null,
                $request->query('date_to') ?: null,
                TableQuery::fromRequest($request),
            );

            return response()->json(['data' => ['columns' => RevisionHistoryRepository::columns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
        }

        $paginator = $this->revisions->fullTable(
            $this->intOrNull($request->query('chapter_id')),
            $request->query('date_from') ?: null,
            $request->query('date_to') ?: null,
            TableQuery::fromRequest($request),
        );

        return response()->json([
            'data' => [
                'columns' => RevisionHistoryRepository::columns(),
                'rows' => collect($paginator->items())->map(fn (ManualRevision $r) => $this->mapRow($r))->all(),
                'meta' => $this->meta($paginator),
            ],
        ]);
    }

    /**
     * GET /api/revision-history/{manualRevision}
     */
    public function show(string $manualRevision): JsonResponse
    {
        if (LegacyDb::isConfigured()) {
            $detail = $this->revisions->legacyDetail($manualRevision);
            abort_if($detail === null, 404);

            return response()->json(['data' => $detail]);
        }

        $model = ManualRevision::query()->with('manualDocument.manualChapter')->findOrFail((int) $manualRevision);

        return response()->json(['data' => $this->mapDetail($model)]);
    }

    /**
     * POST /api/revision-history
     */
    public function store(RevisionHistoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->guardAgainstDuplicate($data);

        $revision = $this->revisions->create($data);
        $revision->load('manualDocument.manualChapter');

        return response()->json(['data' => $this->mapDetail($revision)], 201);
    }

    /**
     * PUT /api/revision-history/{manualRevision}
     */
    public function update(RevisionHistoryRequest $request, ManualRevision $manualRevision): JsonResponse
    {
        $data = $request->validated();
        $this->guardAgainstDuplicate($data, $manualRevision->id);

        $revision = $this->revisions->update($manualRevision, $data);
        $revision->load('manualDocument.manualChapter');

        return response()->json(['data' => $this->mapDetail($revision)]);
    }

    /**
     * DELETE /api/revision-history/{manualRevision}
     */
    public function destroy(ManualRevision $manualRevision): JsonResponse
    {
        $this->revisions->delete($manualRevision);

        return response()->json(['data' => ['ok' => true]]);
    }

    private function guardAgainstDuplicate(array $data, ?int $excludeId = null): void
    {
        $exists = $this->revisions->duplicateExists(
            $data['manual_document_id'],
            $data['revision_no'],
            $data['section'] ?? null,
            $data['reason_revision'] ?? null,
            $data['date_revised'],
            $excludeId,
        );

        if ($exists) {
            throw ValidationException::withMessages([
                'revision_no' => ['This entry already exists.'],
            ]);
        }
    }

    private function intOrNull(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }

    private function mapRow(ManualRevision $r): array
    {
        return [
            'id' => $r->id,
            'arrangement' => $r->arrangement,
            'date_revised' => $r->date_revised->format('Y-m-d'),
            'revision_no' => $r->revision_no,
            'reference_no' => $r->manualDocument?->reference_no ?? '',
            'section' => $r->section,
            'reason_revision' => $r->reason_revision,
            'reviewed_by' => $r->reviewed_by,
            'approved_by' => $r->approved_by,
            'can_edit' => true,
            'can_delete' => true,
        ];
    }

    private function mapDetail(ManualRevision $r): array
    {
        return [
            ...$this->mapRow($r),
            'manual_chapter_id' => $r->manualDocument?->manual_chapter_id,
            'manual_document_id' => $r->manual_document_id,
            'procedure_label' => $r->manualDocument
                ? "({$r->manualDocument->reference_no}) {$r->manualDocument->manual_name}"
                : '',
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
