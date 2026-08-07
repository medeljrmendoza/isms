<?php

namespace App\Repositories\ManualPublish;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

/**
 * Ported from Controllers/Dashboard_sms_version_monitoring.php. Same
 * vessel-summary-grid shape as DrillRepository — see its docblock.
 * "Pending" means: this manual/form applies to the vessel (vessel_access
 * ALL, or the vessel is in the SPECIFIC pivot) and is currently
 * published/active, but the vessel's last synced file_hash doesn't
 * match the current one (or it's never synced at all).
 */
class SmsVersionMonitoringRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => true],
        ['key' => 'procedures', 'label' => 'PROCEDURES', 'sortable' => true],
        ['key' => 'forms', 'label' => 'FORMS/POSTERS', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_sms_version_monitoring.php's
     * count_sms_version_procedure()/count_sms_version_forms(). Same
     * vessel list scoping as DrillRepository::legacyTable() (assigned
     * AND active vessel AND active principal), then per-vessel counts
     * using the "applies to vessel, and vessel's last synced hash
     * doesn't match" logic — see this class's docblock. tb_manual_vessel_access's
     * `manualID` column is reused for chapters, documents, and forms
     * alike (no type discriminator); ported as-is, relying on the same
     * ID-prefix uniqueness convention the rest of this schema depends on.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $eligibleVesselIds = LegacyDb::assignedVesselIds($legacyUserId)
            ->intersect(LegacyDb::activeVesselIdsWithActivePrincipal());

        $documents = DB::connection('legacy')->table('tb_manual_documents')
            ->join('tb_manual_chapter', 'tb_manual_chapter.chapterID', '=', 'tb_manual_documents.chapterID')
            ->where('tb_manual_documents.doc_status', '1')
            ->where('tb_manual_documents.is_published', '1')
            ->where('tb_manual_chapter.status', '1')
            ->get(['tb_manual_documents.manDocID', 'tb_manual_documents.vessel_access', 'tb_manual_documents.file_hash']);

        $forms = DB::connection('legacy')->table('tb_manual_forms')
            ->where('status', '1')
            ->where('is_deleted', '0')
            ->get(['formID', 'vessel_access', 'file_hash']);

        $vesselAccess = DB::connection('legacy')->table('tb_manual_vessel_access')
            ->whereIn('vesID', $eligibleVesselIds)
            ->get(['vesID', 'manualID'])
            ->groupBy('vesID')
            ->map(fn ($rows) => $rows->pluck('manualID')->all());

        $manualSyncs = DB::connection('legacy')->table('tb_vessel_manual')
            ->whereIn('vesID', $eligibleVesselIds)
            ->get(['vesID', 'manualID', 'file_hash'])
            ->groupBy('vesID');

        $formSyncs = DB::connection('legacy')->table('tb_manual_forms_history_vessel')
            ->whereIn('vesID', $eligibleVesselIds)
            ->get(['vesID', 'formID', 'file_hash'])
            ->groupBy('vesID');

        $rows = collect($eligibleVesselIds)->map(function ($vesID) use ($documents, $forms, $vesselAccess, $manualSyncs, $formSyncs, $vessels) {
            $accessible = $vesselAccess->get($vesID, []);
            $vesselManualSyncs = $manualSyncs->get($vesID, collect());
            $vesselFormSyncs = $formSyncs->get($vesID, collect());

            $procedures = $documents
                ->filter(fn ($doc) => $doc->vessel_access === 'ALL' || in_array($doc->manDocID, $accessible, true))
                ->filter(fn ($doc) => ! $vesselManualSyncs->contains(fn ($sync) => $sync->manualID === $doc->manDocID && $sync->file_hash === $doc->file_hash))
                ->count();

            $formsPending = $forms
                ->filter(fn ($form) => $form->vessel_access === 'ALL' || in_array($form->formID, $accessible, true))
                ->filter(fn ($form) => ! $vesselFormSyncs->contains(fn ($sync) => $sync->formID === $form->formID && $sync->file_hash === $form->file_hash))
                ->count();

            $vesselName = $vessels[$vesID] ?? '';

            return ['vessel' => $vesselName, 'procedures' => $procedures, 'forms' => $formsPending, '_sort_vessel' => $vesselName];
        });

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['vessel']), $term));
        }

        $sortMap = ['vessel' => '_sort_vessel', 'procedures' => 'procedures', 'forms' => 'forms'];
        $sortKey = $sortMap[$query->sort ?? 'vessel'] ?? '_sort_vessel';

        $sorted = $rows->sortBy($sortKey, SORT_REGULAR, $query->direction === 'desc')->values()
            ->map(fn (array $r) => collect($r)->except('_sort_vessel')->all());

        $total = $sorted->count();
        $items = $sorted->slice(($query->page - 1) * $query->perPage, $query->perPage)->values()->all();

        return [
            'rows' => $items,
            'meta' => [
                'current_page' => $query->page,
                'last_page' => (int) max(1, ceil($total / $query->perPage)),
                'per_page' => $query->perPage,
                'total' => $total,
            ],
        ];
    }
}
