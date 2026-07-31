<?php

namespace App\Repositories\ManualPublish;

use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\ManualPublish\ManualForm;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ported from Controllers/Sms.php (the "Manuals" browser). Legacy opens
 * each document/form's actual file via an S3 presigned URL — no file
 * storage exists anywhere in this migration, so this stays metadata-
 * only (chapter/document/form tree + search), matching how every other
 * module dropped attachment-viewing. Legacy's per-chapter vessel-access
 * grant (tb_manual_vessel_access keyed directly on a chapterID, not a
 * document) isn't modeled either — a chapter is included here whenever
 * it has at least one visible document, which is the only thing that
 * grant could ever actually be used for. "Tags" search (tb_manual_
 * documents.tags / tb_manual_forms.tags) is dropped too — no tags
 * column exists on either table in this schema.
 */
class ManualBrowserRepository
{
    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /**
     * Ported from index(): $sms_type "ALL" shows every published
     * document/active form regardless of vessel_access; "VESSEL" scopes
     * both to vessel_access='ALL' or a specific grant for $vesselId.
     */
    public function tree(?string $smsType, ?int $vesselId): array
    {
        if ($smsType === null || $smsType === '') {
            return [];
        }

        $documents = $this->visibleDocumentsQuery($smsType, $vesselId)
            ->with('manualChapter', 'forms.vessels')
            ->orderBy('reference_no')
            ->get();

        $chapters = ManualChapter::query()
            ->whereIn('id', $documents->pluck('manual_chapter_id')->unique())
            ->orderBy('reference_no')
            ->get();

        return $chapters->map(fn (ManualChapter $chapter) => [
            'id' => $chapter->id,
            'label' => "({$chapter->reference_no}) {$chapter->chapter_name}",
            'documents' => $documents->where('manual_chapter_id', $chapter->id)
                ->map(fn (ManualDocument $doc) => $this->mapDocument($doc, $smsType, $vesselId))
                ->values()
                ->all(),
        ])->values()->all();
    }

    /** Ported from search_document(). */
    public function search(string $term, ?string $smsType, ?int $vesselId): array
    {
        $like = "%{$term}%";

        $documentResults = $this->visibleDocumentsQuery($smsType, $vesselId)
            ->with('manualChapter')
            ->where(function (Builder $q) use ($like) {
                $q->where('manual_name', 'like', $like)
                    ->orWhere('reference_no', 'like', $like)
                    ->orWhereHas('manualChapter', fn (Builder $c) => $c->where('chapter_name', 'like', $like));
            })
            ->orderBy('reference_no')
            ->get()
            ->map(fn (ManualDocument $d) => [
                'type' => 'document',
                'id' => $d->id,
                'label' => "({$d->reference_no}) {$d->manual_name}",
            ]);

        $formResults = $this->visibleFormsQuery($smsType, $vesselId)
            ->where(function (Builder $q) use ($like) {
                $q->where('file_name', 'like', $like)->orWhere('reference_no', 'like', $like);
            })
            ->orderBy('reference_no')
            ->get()
            ->map(fn (ManualForm $f) => [
                'type' => 'form',
                'id' => $f->id,
                'label' => "({$f->reference_no}) {$f->file_name}",
            ]);

        return $documentResults->concat($formResults)->values()->all();
    }

    private function visibleDocumentsQuery(?string $smsType, ?int $vesselId): Builder
    {
        $query = ManualDocument::query()->where('is_published', true);

        if ($smsType === 'VESSEL') {
            $query->where(function (Builder $q) use ($vesselId) {
                $q->where('vessel_access', 'ALL')
                    ->orWhereHas('vessels', fn (Builder $v) => $v->where('vessels.id', $vesselId));
            });
        }

        return $query;
    }

    private function visibleFormsQuery(?string $smsType, ?int $vesselId): Builder
    {
        $query = ManualForm::query()->where('is_active', true)->where('is_deleted', false);

        if ($smsType === 'VESSEL') {
            $query->where(function (Builder $q) use ($vesselId) {
                $q->where('vessel_access', 'ALL')
                    ->orWhereHas('vessels', fn (Builder $v) => $v->where('vessels.id', $vesselId));
            });
        }

        return $query;
    }

    private function mapDocument(ManualDocument $doc, string $smsType, ?int $vesselId): array
    {
        $forms = $doc->forms->filter(function (ManualForm $form) use ($smsType, $vesselId) {
            if (! $form->is_active || $form->is_deleted) {
                return false;
            }

            if ($smsType !== 'VESSEL') {
                return true;
            }

            return $form->vessel_access === 'ALL' || $form->vessels->contains('id', $vesselId);
        });

        return [
            'id' => $doc->id,
            'reference_no' => $doc->reference_no,
            'manual_name' => $doc->manual_name,
            'date_of_revision' => $doc->date_of_revision->format('Y-m-d'),
            'forms' => $forms->map(fn (ManualForm $f) => [
                'id' => $f->id,
                'reference_no' => $f->reference_no,
                'file_name' => $f->file_name,
            ])->values()->all(),
        ];
    }
}
