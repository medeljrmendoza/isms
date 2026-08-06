<?php

namespace App\Repositories\RevisionHistory;

use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

/** Ported from Controllers/Sms_revision.php. */
class RevisionHistoryRepository
{
    private const COLUMNS = [
        ['key' => 'arrangement', 'label' => 'ORDER', 'sortable' => true],
        ['key' => 'date_revised', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'revision_no', 'label' => 'REVISION NO.', 'sortable' => true],
        ['key' => 'reference_no', 'label' => 'REF NO.', 'sortable' => false],
        ['key' => 'section', 'label' => 'SECTION', 'sortable' => false],
        ['key' => 'reason_revision', 'label' => 'REASON FOR REVISION', 'sortable' => false],
        ['key' => 'reviewed_by', 'label' => 'REVIEWED BY', 'sortable' => true],
        ['key' => 'approved_by', 'label' => 'APPROVED BY', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyChapterOptions(): array
    {
        return DB::connection('legacy')->table('tb_manual_chapter')->orderBy('reference_no')->get()
            ->map(fn ($c) => ['id' => $c->chapterID, 'label' => "({$c->reference_no}) {$c->chapter_name}"])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyDocumentOptionsForChapter(string $chapterId): array
    {
        return DB::connection('legacy')->table('tb_manual_documents')->where('chapterID', $chapterId)->orderBy('reference_no')->get()
            ->map(fn ($d) => ['id' => $d->manDocID, 'label' => "({$d->reference_no}) {$d->manual_name}"])
            ->all();
    }

    /**
     * Ported from Sms_revision.php's index(): the same date_revised /
     * arrangement default sort and chapter/date filters, reading
     * tb_manual_revisions directly from the legacy connection. No vessel
     * scoping — this is global procedure metadata, not per-vessel data.
     */
    public function legacyFullTable(?string $chapterId, ?string $dateFrom, ?string $dateTo, TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_manual_revisions')
            ->leftJoin('tb_manual_documents', 'tb_manual_documents.manDocID', '=', 'tb_manual_revisions.manualID')
            ->select([
                'tb_manual_revisions.revisionID',
                'tb_manual_revisions.arrangement',
                'tb_manual_revisions.date_revised',
                'tb_manual_revisions.revision_no',
                'tb_manual_revisions.section',
                'tb_manual_revisions.reason_revision',
                'tb_manual_revisions.reviewed_by',
                'tb_manual_revisions.approved_by',
                'tb_manual_documents.reference_no',
            ]);

        if ($chapterId !== null && $chapterId !== '' && $chapterId !== 'ALL') {
            $builder->where('tb_manual_documents.chapterID', $chapterId);
        }

        if ($dateFrom !== null) {
            $builder->where('tb_manual_revisions.date_revised', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $builder->where('tb_manual_revisions.date_revised', '<=', $dateTo);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_manual_revisions.revision_no', 'like', $term)
                    ->orWhere('tb_manual_revisions.section', 'like', $term)
                    ->orWhere('tb_manual_revisions.reason_revision', 'like', $term)
                    ->orWhere('tb_manual_revisions.reviewed_by', 'like', $term)
                    ->orWhere('tb_manual_revisions.approved_by', 'like', $term)
                    ->orWhere('tb_manual_documents.reference_no', 'like', $term);
            });
        }

        $sortMap = [
            'arrangement' => 'tb_manual_revisions.arrangement',
            'date_revised' => 'tb_manual_revisions.date_revised',
            'revision_no' => 'tb_manual_revisions.revision_no',
            'reviewed_by' => 'tb_manual_revisions.reviewed_by',
            'approved_by' => 'tb_manual_revisions.approved_by',
        ];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_manual_revisions.date_revised';

        $paginator = $builder
            ->orderBy($sort, $query->direction)
            ->orderBy('tb_manual_revisions.arrangement', $query->direction)
            ->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->revisionID,
            'arrangement' => $r->arrangement,
            'date_revised' => $r->date_revised,
            'revision_no' => $r->revision_no,
            'reference_no' => $r->reference_no,
            'section' => $r->section,
            'reason_revision' => $r->reason_revision,
            'reviewed_by' => $r->reviewed_by,
            'approved_by' => $r->approved_by,
            'can_edit' => false,
            'can_delete' => false,
        ])->all();

        return [
            'rows' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    /** Same shape as legacyFullTable()'s row, plus the Add/Edit-form-only fields, reading the legacy connection. */
    public function legacyDetail(string $revisionId): ?array
    {
        $r = DB::connection('legacy')->table('tb_manual_revisions')
            ->leftJoin('tb_manual_documents', 'tb_manual_documents.manDocID', '=', 'tb_manual_revisions.manualID')
            ->where('tb_manual_revisions.revisionID', $revisionId)
            ->select(['tb_manual_revisions.*', 'tb_manual_documents.reference_no', 'tb_manual_documents.manual_name', 'tb_manual_documents.chapterID'])
            ->first();

        if ($r === null) {
            return null;
        }

        return [
            'id' => $r->revisionID,
            'arrangement' => $r->arrangement,
            'date_revised' => $r->date_revised,
            'revision_no' => $r->revision_no,
            'reference_no' => $r->reference_no,
            'section' => $r->section,
            'reason_revision' => $r->reason_revision,
            'reviewed_by' => $r->reviewed_by,
            'approved_by' => $r->approved_by,
            'can_edit' => false,
            'can_delete' => false,
            'manual_chapter_id' => null,
            'manual_document_id' => null,
            'procedure_label' => $r->reference_no !== null ? "({$r->reference_no}) {$r->manual_name}" : '',
        ];
    }
}
