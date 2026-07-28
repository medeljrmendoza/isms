<?php

namespace App\Repositories\VesselDocumentation;

use App\Repositories\CompanyDocumentation\CompanyDocumentationRepository;
use App\Repositories\Drills\DrillRepository;

use App\Models\Vessel;
use App\Models\VesselDocumentation\VesselDocumentExpirySetting;
use App\Models\VesselDocumentation\VesselDocumentRecord;
use App\Support\TableQuery;
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

    public static function columns(): array
    {
        return self::COLUMNS;
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
