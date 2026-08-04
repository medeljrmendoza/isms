<?php

namespace App\Repositories\ManualPublish;

use App\Models\ManualPublish\ManualDocument;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ManualDocumentPublishRepository
{
    private const COLUMNS = [
        ['key' => 'chapter', 'label' => 'CHAPTER', 'sortable' => false],
        ['key' => 'manual', 'label' => 'MANUAL', 'sortable' => false],
        ['key' => 'date_of_revision', 'label' => 'DATE', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_sms_publish_manual.php's
     * loadSMSPublishData(). Legacy's attachment-view link (S3) and
     * Publish action button are dropped — this dashlet is read-only,
     * consistent with every other migrated dashlet.
     */
    public function pendingQuery(): Builder
    {
        return ManualDocument::query()
            ->with('manualChapter')
            ->where('is_published', false);
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('manual_name', 'like', $term)
                    ->orWhere('reference_no', 'like', $term)
                    ->orWhere('date_of_revision', 'like', $term)
                    ->orWhereHas('manualChapter', fn (Builder $c) => $c->where('chapter_name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_revision';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Dashboard_sms_publish_manual.php's
     * loadSMSPublishData(): unpublished manual documents, company-wide
     * (no vessel scoping in legacy's own query).
     */
    public function legacyTable(TableQuery $query): array
    {
        $builder = DB::connection('legacy')->table('tb_manual_documents')
            ->join('tb_manual_chapter', 'tb_manual_chapter.chapterID', '=', 'tb_manual_documents.chapterID')
            ->where('tb_manual_documents.is_published', '!=', '1')
            ->select([
                'tb_manual_chapter.reference_no as chapter_reference_no',
                'tb_manual_chapter.chapter_name',
                'tb_manual_documents.reference_no as manual_reference_no',
                'tb_manual_documents.manual_name',
                'tb_manual_documents.dateof_revision',
            ]);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_manual_documents.dateof_revision', 'like', $term)
                    ->orWhere('tb_manual_chapter.reference_no', 'like', $term)
                    ->orWhere('tb_manual_chapter.chapter_name', 'like', $term)
                    ->orWhere('tb_manual_documents.reference_no', 'like', $term)
                    ->orWhere('tb_manual_documents.manual_name', 'like', $term);
            });
        }

        $sortMap = ['date_of_revision' => 'tb_manual_documents.dateof_revision'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_manual_documents.dateof_revision';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'chapter' => "({$r->chapter_reference_no}) {$r->chapter_name}",
            'manual' => "({$r->manual_reference_no}) {$r->manual_name}",
            'date_of_revision' => $r->dateof_revision,
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
}
