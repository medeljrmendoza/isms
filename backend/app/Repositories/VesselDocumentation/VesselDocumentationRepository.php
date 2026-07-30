<?php

namespace App\Repositories\VesselDocumentation;

use App\Models\Vessel;
use App\Models\VesselDocumentation\VesselDocument;
use App\Models\VesselDocumentation\VesselDocumentExpirySetting;
use App\Models\VesselDocumentation\VesselDocumentRecord;
use App\Models\VesselDocumentation\VesselDocumentType;
use App\Repositories\CompanyDocumentation\CompanyDocumentationRepository;
use App\Repositories\Drills\DrillRepository;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Ported from Controllers/Dashboard_vessel_documentation.php. Same
 * vessel-summary-grid shape as DrillRepository — see its docblock.
 *
 * Expiring/expired reuses the same warning-status logic as
 * CompanyDocumentationRepository (own separate num_month setting,
 * mirroring legacy's own separate tb_document_expiring table).
 *
 * "New attachment from vessel/shore" replicates legacy's file-hash
 * comparison directly: legacy tracks this via separate per-vessel and
 * per-shore upload-history tables (S3-backed); this migration doesn't
 * model file attachments or S3 sync history anywhere else, so it's
 * simplified to the two latest-known-hash columns the comparison
 * actually needs (vessel_file_hash, shore_file_hash) rather than full
 * history tables. Legacy's own two counts already overlap heavily in
 * practice (both are essentially "vessel and shore hashes disagree"),
 * so this doesn't lose any real distinction.
 */
class VesselDocumentationRepository
{
    private const COLUMNS = [
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => true],
        ['key' => 'expiring', 'label' => 'EXPIRING', 'sortable' => true],
        ['key' => 'expired', 'label' => 'EXPIRED', 'sortable' => true],
        ['key' => 'new_from_vessel', 'label' => 'NEW FROM VESSEL', 'sortable' => true],
        ['key' => 'new_from_shore', 'label' => 'NEW FROM SHORE', 'sortable' => true],
    ];

    /**
     * The full module list's column set — see Controllers/Vessel_documentation.php's
     * loadDocumentData(). Not ported: PAGE NO./ID (the pl_vessel_document
     * catalog's print-layout fields), ATTACHMENT/ARCHIVED (depend on the
     * S3 upload + history-archive infra this migration doesn't model —
     * see the add_full_record_fields migration's docblock), and ORIGIN
     * (SHORE/VESSEL — meaningless without the two-sided upload flow it
     * used to gate).
     */
    private const MODULE_COLUMNS = [
        ['key' => 'document_type', 'label' => 'TYPE', 'sortable' => false],
        ['key' => 'document', 'label' => 'DOCUMENT', 'sortable' => false],
        ['key' => 'doc_number', 'label' => 'CERT. NO.', 'sortable' => true],
        ['key' => 'issuing_body', 'label' => 'ISSUING BODY', 'sortable' => true],
        ['key' => 'date_issued', 'label' => 'ISSUED', 'sortable' => true],
        ['key' => 'date_expired', 'label' => 'EXPIRED', 'sortable' => true],
        ['key' => 'is_printer_friendly', 'label' => 'PF', 'sortable' => false],
        ['key' => 'warning_status', 'label' => 'WARNING', 'sortable' => false],
        ['key' => 'is_active', 'label' => 'STATUS', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    public static function moduleColumns(): array
    {
        return self::MODULE_COLUMNS;
    }

    /** @return Collection<int, array{vessel: Vessel, expiring: int, expired: int, new_from_vessel: int, new_from_shore: int}> */
    public function summaries(): Collection
    {
        $numMonths = VesselDocumentExpirySetting::query()->value('num_month') ?? 3;
        $today = Carbon::today();

        $records = VesselDocumentRecord::query()
            ->with('vesselDocument.vesselDocumentType')
            ->whereHas('vesselDocument', fn ($q) => $q->where('is_active', true)->where('is_deleted', false))
            ->whereHas('vesselDocument.vesselDocumentType', fn ($q) => $q->where('is_active', true)->where('is_deleted', false))
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->get()
            ->groupBy('vessel_id');

        return Vessel::query()->orderBy('name')->get()->map(function (Vessel $vessel) use ($records, $numMonths, $today) {
            $vesselRecords = $records->get($vessel->id, collect());

            $expiring = 0;
            $expired = 0;
            $newFromVessel = 0;
            $newFromShore = 0;

            foreach ($vesselRecords as $record) {
                $status = $this->warningStatus($record, $numMonths, $today);
                $expiring += $status === 1 ? 1 : 0;
                $expired += $status === 2 ? 1 : 0;

                if ($record->vessel_file_hash && $record->vessel_file_hash !== $record->shore_file_hash) {
                    $newFromVessel++;
                }

                if ($record->shore_file_hash && $record->shore_file_hash !== $record->vessel_file_hash) {
                    $newFromShore++;
                }
            }

            return [
                'vessel' => $vessel,
                'expiring' => $expiring,
                'expired' => $expired,
                'new_from_vessel' => $newFromVessel,
                'new_from_shore' => $newFromShore,
            ];
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
            'expiring' => fn (array $row) => $row['expiring'],
            'expired' => fn (array $row) => $row['expired'],
            'new_from_vessel' => fn (array $row) => $row['new_from_vessel'],
            'new_from_shore' => fn (array $row) => $row['new_from_shore'],
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

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /**
     * Types with at least one non-deleted record for this vessel — the
     * index filter dropdown. Ported from get_type($vesselID): legacy
     * scopes pl_vessel_document_type itself by vesID (per-vessel
     * customizable types), but VesselDocumentType is a shared global
     * lookup in this migration (same simplification already shipped in
     * the dashlet), so this derives the per-vessel list from existing
     * records instead.
     */
    public function documentTypeOptionsForVessel(int $vesselId): array
    {
        return VesselDocumentType::query()
            ->whereHas('vesselDocuments.records', fn (Builder $q) => $q->where('vessel_id', $vesselId)->where('is_deleted', false))
            ->orderBy('name')
            ->get()
            ->map(fn (VesselDocumentType $t) => ['id' => $t->id, 'label' => $t->name])
            ->all();
    }

    /**
     * Catalog documents this vessel doesn't already have a live record
     * for — the Add form's Document dropdown. Ported from
     * get_document_by_vessel(); legacy's own get_document() isn't in the
     * exported controller source, but the "Select Document" single-pick
     * UI (add_vessel_documentation_v.php) only makes sense if already-
     * recorded documents are excluded, so that's what this replicates.
     */
    public function catalogOptionsForVessel(int $vesselId): array
    {
        return VesselDocument::query()
            ->with('vesselDocumentType')
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->whereDoesntHave('records', fn (Builder $q) => $q->where('vessel_id', $vesselId)->where('is_deleted', false))
            ->get()
            ->sortBy(fn (VesselDocument $d) => $d->vesselDocumentType->name.' — '.$d->name)
            ->map(fn (VesselDocument $d) => ['id' => $d->id, 'label' => $d->vesselDocumentType->name.' — '.$d->name])
            ->values()
            ->all();
    }

    /** Ported from loadDocumentData(). */
    public function fullTable(int $vesselId, ?int $typeId, TableQuery $query): LengthAwarePaginator
    {
        $numMonths = VesselDocumentExpirySetting::query()->value('num_month') ?? 3;
        $today = Carbon::today();

        $builder = VesselDocumentRecord::query()
            ->with('vesselDocument.vesselDocumentType')
            ->where('vessel_id', $vesselId)
            ->where('is_deleted', false);

        if ($typeId !== null) {
            $builder->whereHas('vesselDocument', fn (Builder $q) => $q->where('vessel_document_type_id', $typeId));
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('doc_number', 'like', $term)
                    ->orWhere('issuing_body', 'like', $term)
                    ->orWhereHas('vesselDocument', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_expired';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        // warning_status is computed, not a DB column — attach it after
        // pagination the same way summaries() does, on this page's rows only.
        $paginator->getCollection()->each(function (VesselDocumentRecord $record) use ($numMonths, $today) {
            $record->warning_status = $this->warningStatus($record, $numMonths, $today);
        });

        return $paginator;
    }

    /** Ported from add_document()'s insert branch (vessel_docID == ""). */
    public function create(array $data): VesselDocumentRecord
    {
        return VesselDocumentRecord::create([
            ...$data,
            'is_active' => true,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_document()'s edit branch. vessel_id/vessel_document_id
     * are frozen at creation time, same as legacy re-reading them from the
     * existing row rather than trusting the form.
     */
    public function update(VesselDocumentRecord $record, array $data): VesselDocumentRecord
    {
        unset($data['vessel_id'], $data['vessel_document_id']);

        $record->update($data);

        return $record;
    }

    /** Ported from vessel_documentation_status(): flips active/inactive. */
    public function toggleStatus(VesselDocumentRecord $record): VesselDocumentRecord
    {
        $record->update(['is_active' => ! $record->is_active]);

        return $record;
    }

    /** Ported from delete_documentation(): soft delete. */
    public function delete(VesselDocumentRecord $record): void
    {
        $record->update(['is_deleted' => true]);
    }

    /** 0 = fine, 1 = expiring soon, 2 = expired. Same logic as CompanyDocumentationRepository. */
    private function warningStatus(VesselDocumentRecord $record, int $numMonths, Carbon $today): int
    {
        if ($record->date_expired === null) {
            return 0;
        }

        if ($record->date_expired->lte($today)) {
            return 2;
        }

        if ($record->date_range_from === null) {
            return $today->gte($record->date_expired->copy()->subMonths($numMonths)) ? 1 : 0;
        }

        return $today->between($record->date_range_from, $record->date_range_to) ? 1 : 0;
    }
}
