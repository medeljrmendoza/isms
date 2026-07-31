<?php

namespace App\Repositories\RevisionHistory;

use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\RevisionHistory\ManualRevision;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

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

    /** @return array<int, array{id:int,label:string}> */
    public function chapterOptions(): array
    {
        return ManualChapter::query()->orderBy('reference_no')->get()
            ->map(fn (ManualChapter $c) => ['id' => $c->id, 'label' => "({$c->reference_no}) {$c->chapter_name}"])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function documentOptionsForChapter(int $chapterId): array
    {
        return ManualDocument::query()->where('manual_chapter_id', $chapterId)->orderBy('reference_no')->get()
            ->map(fn (ManualDocument $d) => ['id' => $d->id, 'label' => "({$d->reference_no}) {$d->manual_name}"])
            ->all();
    }

    /**
     * Ported from index()/loadData(): filter by the procedure's manual
     * chapter and an optional date_revised range. Default sort mirrors
     * legacy's "ORDER BY date_revised ASC, arrangement ASC".
     */
    public function fullTable(?int $chapterId, ?string $dateFrom, ?string $dateTo, TableQuery $query): LengthAwarePaginator
    {
        $builder = ManualRevision::query()->with('manualDocument.manualChapter');

        if ($chapterId !== null) {
            $builder->whereHas('manualDocument', fn (Builder $d) => $d->where('manual_chapter_id', $chapterId));
        }

        if ($dateFrom !== null) {
            $builder->whereDate('date_revised', '>=', $dateFrom);
        }

        if ($dateTo !== null) {
            $builder->whereDate('date_revised', '<=', $dateTo);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('revision_no', 'like', $term)
                    ->orWhere('section', 'like', $term)
                    ->orWhere('reason_revision', 'like', $term)
                    ->orWhere('reviewed_by', 'like', $term)
                    ->orWhere('approved_by', 'like', $term)
                    ->orWhereHas('manualDocument', fn (Builder $d) => $d->where('reference_no', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_revised';

        return $builder->orderBy($sort, $query->direction)
            ->orderBy('arrangement', $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_item()'s duplicate check: the same procedure +
     * revision no. + section + reason + date combination is rejected.
     */
    public function duplicateExists(int $manualDocumentId, string $revisionNo, ?string $section, ?string $reasonRevision, string $dateRevised, ?int $excludeId = null): bool
    {
        return ManualRevision::query()
            ->where('manual_document_id', $manualDocumentId)
            ->where('revision_no', $revisionNo)
            ->where('section', $section)
            ->where('reason_revision', $reasonRevision)
            ->whereDate('date_revised', $dateRevised)
            ->when($excludeId !== null, fn (Builder $q) => $q->where('id', '!=', $excludeId))
            ->exists();
    }

    public function create(array $data): ManualRevision
    {
        return ManualRevision::create($data);
    }

    /** Ported from the edit view: manual_document_id is frozen once created. */
    public function update(ManualRevision $revision, array $data): ManualRevision
    {
        unset($data['manual_document_id']);
        $revision->update($data);

        return $revision;
    }

    /** Ported from delete_sms_revision(): hard delete. */
    public function delete(ManualRevision $revision): void
    {
        $revision->delete();
    }
}
