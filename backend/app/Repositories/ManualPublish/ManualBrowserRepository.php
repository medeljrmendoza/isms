<?php

namespace App\Repositories\ManualPublish;

use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\ManualPublish\ManualForm;
use App\Models\Vessel;
use App\Support\LegacyDb;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /**
     * Ported from Controllers/Sms.php's index(): "ALL" shows every
     * published document with an active connected form regardless of
     * vessel_access; a specific vessel scopes both to vessel_access='ALL'
     * or an explicit tb_manual_vessel_access grant for that vessel. Same
     * simplifications as tree()'s docblock: no file-path/tb_manual_history
     * published-exception check (no file storage modeled anywhere in this
     * migration), and chapters are derived from their visible documents
     * rather than queried via their own separate vessel-access grant —
     * both decisions already established for the local path.
     */
    public function legacyTree(?string $smsType, ?string $vesselId): array
    {
        if ($smsType === null || $smsType === '') {
            return [];
        }

        $documents = $this->legacyVisibleDocumentsQuery($smsType, $vesselId)
            ->orderBy('doc_arrangement')
            ->get();

        if ($documents->isEmpty()) {
            return [];
        }

        $forms = $this->legacyVisibleFormsQuery($smsType, $vesselId)
            ->orderBy('tb_manual_forms.reference_no')
            ->get()
            ->groupBy('manDocID');

        $chapters = DB::connection('legacy')->table('tb_manual_chapter')
            ->whereIn('chapterID', $documents->pluck('chapterID')->unique())
            ->orderBy('arrangement')
            ->get();

        return $chapters->map(fn ($chapter) => [
            'id' => $chapter->chapterID,
            'label' => "({$chapter->reference_no}) {$chapter->chapter_name}",
            'documents' => $documents->where('chapterID', $chapter->chapterID)
                ->map(fn ($doc) => [
                    'id' => $doc->manDocID,
                    'reference_no' => $doc->reference_no,
                    'manual_name' => $doc->manual_name,
                    'date_of_revision' => $doc->dateof_revision,
                    'forms' => ($forms->get($doc->manDocID) ?? collect())
                        ->map(fn ($f) => ['id' => $f->formID, 'reference_no' => $f->reference_no, 'file_name' => $f->file_name])
                        ->values()->all(),
                ])->values()->all(),
        ])->values()->all();
    }

    /** Ported from search_document(). */
    public function legacySearch(string $term, ?string $smsType, ?string $vesselId): array
    {
        $like = "%{$term}%";

        $documentResults = $this->legacyVisibleDocumentsQuery($smsType, $vesselId)
            ->leftJoin('tb_manual_chapter', 'tb_manual_chapter.chapterID', '=', 'tb_manual_documents.chapterID')
            ->where(function ($q) use ($like) {
                $q->where('tb_manual_documents.manual_name', 'like', $like)
                    ->orWhere('tb_manual_documents.reference_no', 'like', $like)
                    ->orWhere('tb_manual_chapter.chapter_name', 'like', $like);
            })
            ->orderBy('tb_manual_documents.reference_no')
            ->get(['tb_manual_documents.manDocID', 'tb_manual_documents.reference_no', 'tb_manual_documents.manual_name'])
            ->map(fn ($d) => ['type' => 'document', 'id' => $d->manDocID, 'label' => "({$d->reference_no}) {$d->manual_name}"]);

        $formResults = $this->legacyVisibleFormsQuery($smsType, $vesselId)
            ->where(function ($q) use ($like) {
                $q->where('tb_manual_forms.file_name', 'like', $like)->orWhere('tb_manual_forms.reference_no', 'like', $like);
            })
            ->orderBy('tb_manual_forms.reference_no')
            ->get()
            ->unique('formID')
            ->map(fn ($f) => ['type' => 'form', 'id' => $f->formID, 'label' => "({$f->reference_no}) {$f->file_name}"]);

        return $documentResults->concat($formResults)->values()->all();
    }

    private function legacyVisibleDocumentsQuery(?string $smsType, ?string $vesselId)
    {
        $query = DB::connection('legacy')->table('tb_manual_documents')
            ->where('tb_manual_documents.doc_status', 1)
            ->where('tb_manual_documents.is_published', '!=', '0');

        if ($smsType === 'VESSEL') {
            $query->where(function ($q) use ($vesselId) {
                $q->where('tb_manual_documents.vessel_access', 'ALL')
                    ->orWhereIn('tb_manual_documents.manDocID', function ($sub) use ($vesselId) {
                        $sub->select('manualID')->from('tb_manual_vessel_access')->where('vesID', $vesselId);
                    });
            });
        }

        return $query;
    }

    private function legacyVisibleFormsQuery(?string $smsType, ?string $vesselId)
    {
        $query = DB::connection('legacy')->table('tb_manual_forms_connections')
            ->join('tb_manual_forms', 'tb_manual_forms.formID', '=', 'tb_manual_forms_connections.formID')
            ->where('tb_manual_forms.status', '1')
            ->where('tb_manual_forms.is_deleted', '0')
            ->select(['tb_manual_forms.formID', 'tb_manual_forms.reference_no', 'tb_manual_forms.file_name', 'tb_manual_forms_connections.manDocID']);

        if ($smsType === 'VESSEL') {
            $query->where(function ($q) use ($vesselId) {
                $q->where('tb_manual_forms.vessel_access', 'ALL')
                    ->orWhereIn('tb_manual_forms.formID', function ($sub) use ($vesselId) {
                        $sub->select('manualID')->from('tb_manual_vessel_access')->where('vesID', $vesselId);
                    });
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
