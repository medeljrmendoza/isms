<?php

namespace App\Repositories\ManualPublish;

use App\Support\LegacyDb;
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
    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /**
     * Ported from Controllers/Sms.php's index(): "ALL" shows every
     * published document with an active connected form regardless of
     * vessel_access; a specific vessel scopes both to vessel_access='ALL'
     * or an explicit tb_manual_vessel_access grant for that vessel.
     * Chapters are derived from their visible documents rather than
     * queried via their own separate vessel-access grant.
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
}
