<?php

namespace App\Repositories;

use App\Models\ManualDocument;
use App\Models\ManualForm;
use App\Models\Vessel;
use App\Models\VesselFormSync;
use App\Models\VesselManualSync;
use App\Support\TableQuery;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

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

    /** @return Collection<int, array{vessel: Vessel, procedures: int, forms: int}> */
    public function summaries(): Collection
    {
        $documents = ManualDocument::query()->where('is_published', true)->with('vessels')->get();
        $forms = ManualForm::query()->where('is_active', true)->where('is_deleted', false)->with('vessels')->get();
        $manualSyncs = VesselManualSync::query()->get()->groupBy('vessel_id');
        $formSyncs = VesselFormSync::query()->get()->groupBy('vessel_id');

        return Vessel::query()->orderBy('name')->get()->map(function (Vessel $vessel) use ($documents, $forms, $manualSyncs, $formSyncs) {
            $vesselManualSyncs = $manualSyncs->get($vessel->id, collect());
            $vesselFormSyncs = $formSyncs->get($vessel->id, collect());

            $procedures = $documents
                ->filter(fn (ManualDocument $doc) => $doc->vessel_access === 'ALL' || $doc->vessels->contains('id', $vessel->id))
                ->filter(fn (ManualDocument $doc) => ! $vesselManualSyncs->contains(fn ($sync) => $sync->manual_document_id === $doc->id && $sync->file_hash === $doc->file_hash))
                ->count();

            $formsPending = $forms
                ->filter(fn (ManualForm $form) => $form->vessel_access === 'ALL' || $form->vessels->contains('id', $vessel->id))
                ->filter(fn (ManualForm $form) => ! $vesselFormSyncs->contains(fn ($sync) => $sync->manual_form_id === $form->id && $sync->file_hash === $form->file_hash))
                ->count();

            return ['vessel' => $vessel, 'procedures' => $procedures, 'forms' => $formsPending];
        });
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $rows = $this->summaries();

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['vessel']->display_name), $term));
        }

        $sortable = [
            'vessel' => fn (array $row) => mb_strtolower($row['vessel']->display_name),
            'procedures' => fn (array $row) => $row['procedures'],
            'forms' => fn (array $row) => $row['forms'],
        ];
        $sortKey = $sortable[$query->sort ?? 'vessel'] ?? $sortable['vessel'];

        $sorted = $rows->sortBy($sortKey, SORT_REGULAR, $query->direction === 'desc')->values();

        $total = $sorted->count();
        $items = $sorted->slice(($query->page - 1) * $query->perPage, $query->perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $total,
            $query->perPage,
            $query->page,
            ['path' => LengthAwarePaginator::resolveCurrentPath()],
        );
    }
}
