<?php

namespace App\Http\Controllers\Api\RevisionHistory;

use App\Http\Controllers\Controller;
use App\Repositories\RevisionHistory\RevisionHistoryRepository;
use App\Support\TableQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ported from Controllers/Sms_revision.php. Read-only: Add/Edit/Delete
 * never had a legacy write-back path built, so they're not offered
 * here — see RevisionHistoryRepository.
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
                'chapters' => $this->revisions->legacyChapterOptions(),
            ],
        ]);
    }

    /**
     * GET /api/revision-history/document-options?chapter_id=
     */
    public function documentOptions(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->revisions->legacyDocumentOptionsForChapter((string) $request->query('chapter_id')),
        ]);
    }

    /**
     * GET /api/revision-history?chapter_id=&date_from=&date_to=&...
     */
    public function index(Request $request): JsonResponse
    {
        $result = $this->revisions->legacyFullTable(
            $this->stringOrNull($request->query('chapter_id')),
            $request->query('date_from') ?: null,
            $request->query('date_to') ?: null,
            TableQuery::fromRequest($request),
        );

        return response()->json(['data' => ['columns' => RevisionHistoryRepository::columns(), 'rows' => $result['rows'], 'meta' => $result['meta']]]);
    }

    /**
     * GET /api/revision-history/{manualRevision}
     */
    public function show(string $manualRevision): JsonResponse
    {
        $detail = $this->revisions->legacyDetail($manualRevision);
        abort_if($detail === null, 404);

        return response()->json(['data' => $detail]);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
