<?php

namespace App\Repositories\VesselDocumentation;

use App\Models\Vessel;
use App\Models\VesselDocumentation\VesselDocument;
use App\Models\VesselDocumentation\VesselDocumentExpirySetting;
use App\Models\VesselDocumentation\VesselDocumentRecord;
use App\Models\VesselDocumentation\VesselDocumentType;
use App\Repositories\CompanyDocumentation\CompanyDocumentationRepository;
use App\Repositories\Drills\DrillRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

    /**
     * Ported from Controllers/Dashboard_vessel_documentation.php: same
     * vessel list scoping as PmsRepository::legacyTable() (assigned AND
     * active vessel AND active principal), then per-vessel expiring/
     * expired/new-from-vessel/new-from-shore counts computed the same
     * way as the local `summaries()` — see this class's docblock for why
     * the file-hash comparison is simplified to the two latest-known
     * hashes rather than full history tables. The MEMBER-only
     * `is_external` gate is dropped per the no-roles-yet precedent used
     * everywhere else in this migration.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $eligibleVesselIds = LegacyDb::assignedVesselIds($legacyUserId)
            ->intersect(LegacyDb::activeVesselIdsWithActivePrincipal());

        $numMonths = (int) (DB::connection('legacy')->table('tb_document_expiring')->where('expID', 1)->value('num_month') ?? 3);

        $records = DB::connection('legacy')->table('tb_vessel_documentation')
            ->join('pl_vessel_document', 'pl_vessel_document.vesDocID', '=', 'tb_vessel_documentation.docID')
            ->join('pl_vessel_document_type', 'pl_vessel_document_type.vesDocTypeID', '=', 'pl_vessel_document.vesDocTypeID')
            ->where('pl_vessel_document_type.status', '1')
            ->where('pl_vessel_document_type.is_deleted', '0')
            ->where('pl_vessel_document.status', '1')
            ->where('pl_vessel_document.is_deleted', '0')
            ->where('tb_vessel_documentation.status', '1')
            ->where('tb_vessel_documentation.is_deleted', '0')
            ->whereIn('tb_vessel_documentation.vesID', $eligibleVesselIds)
            ->get([
                'tb_vessel_documentation.vessel_docID',
                'tb_vessel_documentation.vesID',
                'tb_vessel_documentation.date_expired',
                'tb_vessel_documentation.date_range_from',
                'tb_vessel_documentation.date_range_to',
                'tb_vessel_documentation.file_hash as shore_file_hash',
            ]);

        $vesselDocIds = $records->pluck('vessel_docID')->all();
        $latestVesselHashes = $vesselDocIds === []
            ? collect()
            : DB::connection('legacy')->table('tb_vessel_documentation_history')
                ->whereIn('vessel_docID', $vesselDocIds)
                ->orderByDesc('arrangement')
                ->get(['vessel_docID', 'file_hash'])
                ->unique('vessel_docID')
                ->keyBy('vessel_docID');

        $today = Carbon::today();
        $recordsByVessel = $records->groupBy('vesID');

        $rows = collect($eligibleVesselIds)->map(function ($vesID) use ($recordsByVessel, $latestVesselHashes, $numMonths, $today, $vessels) {
            $expiring = 0;
            $expired = 0;
            $newFromVessel = 0;
            $newFromShore = 0;

            foreach ($recordsByVessel->get($vesID, collect()) as $r) {
                if ($r->date_expired !== '0000-00-00') {
                    $expiredDate = Carbon::parse($r->date_expired);

                    if ($expiredDate->lte($today)) {
                        $expired++;
                    } elseif ($r->date_range_from === '0000-00-00') {
                        if ($today->gte($expiredDate->copy()->subMonths($numMonths))) {
                            $expiring++;
                        }
                    } elseif ($today->between(Carbon::parse($r->date_range_from), Carbon::parse($r->date_range_to))) {
                        $expiring++;
                    }
                }

                $vesselFileHash = $latestVesselHashes->get($r->vessel_docID)?->file_hash ?? '';
                $shoreFileHash = $r->shore_file_hash ?? '';

                if ($vesselFileHash !== '' && $vesselFileHash !== $shoreFileHash) {
                    $newFromVessel++;
                }

                if ($shoreFileHash !== '' && $shoreFileHash !== $vesselFileHash) {
                    $newFromShore++;
                }
            }

            $vesselName = $vessels[$vesID] ?? '';

            return [
                'vessel' => $vesselName,
                'expiring' => $expiring,
                'expired' => $expired,
                'new_from_vessel' => $newFromVessel,
                'new_from_shore' => $newFromShore,
                '_sort_vessel' => $vesselName,
            ];
        });

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower($row['vessel']), $term));
        }

        $sortMap = ['vessel' => '_sort_vessel', 'expiring' => 'expiring', 'expired' => 'expired', 'new_from_vessel' => 'new_from_vessel', 'new_from_shore' => 'new_from_shore'];
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /**
     * Ported from Controllers/Vessel_documentation.php's index() (the
     * TYPE filter dropdown). Unlike the local VesselDocumentType lookup
     * (a shared global catalog, per documentTypeOptionsForVessel()'s
     * docblock), legacy's pl_vessel_document_type is itself vessel-
     * scoped (has its own vesID column) — so this queries it directly
     * rather than deriving the list from existing records.
     */
    public function legacyDocumentTypeOptionsForVessel(string $vesselId): array
    {
        return DB::connection('legacy')->table('pl_vessel_document_type')
            ->where('vesID', $vesselId)
            ->where('status', '1')
            ->where('is_deleted', '0')
            ->orderBy('document_type_name')
            ->get(['vesDocTypeID', 'document_type_name'])
            ->map(fn ($t) => ['id' => $t->vesDocTypeID, 'label' => $t->document_type_name])
            ->all();
    }

    /**
     * Catalog documents this vessel doesn't already have a live record
     * for — same "get_document()" interpretation as catalogOptionsForVessel()'s
     * docblock, applied against legacy's own vessel-scoped pl_vessel_document.
     */
    public function legacyDocumentOptionsForVessel(string $vesselId): array
    {
        return DB::connection('legacy')->table('pl_vessel_document')
            ->where('vesID', $vesselId)
            ->where('status', '1')
            ->where('is_deleted', '0')
            ->whereNotIn('vesDocID', function ($sub) use ($vesselId) {
                $sub->select('docID')->from('tb_vessel_documentation')
                    ->where('vesID', $vesselId)
                    ->where('is_deleted', '0');
            })
            ->orderBy('document_name')
            ->get(['vesDocID', 'document_name'])
            ->map(fn ($d) => ['id' => $d->vesDocID, 'label' => $d->document_name])
            ->all();
    }

    /**
     * Ported from loadDocumentData(). The MEMBER-only is_external gate is
     * dropped per the no-roles-yet precedent used everywhere else in this
     * migration (same decision as legacyTable()'s docblock). Default sort
     * replicates legacy's own DataTable fallback order (status_test DESC,
     * document_type_name ASC, doc_ID ASC) exactly, since real ordering
     * data is available; an explicit TableQuery sort overrides it.
     */
    public function legacyFullTable(string $vesselId, ?string $typeId, TableQuery $query): array
    {
        $numMonths = (int) (DB::connection('legacy')->table('tb_document_expiring')->where('expID', 1)->value('num_month') ?? 3);
        $warningCase = self::legacyWarningCaseSql('tb_vessel_documentation', $numMonths);

        $builder = DB::connection('legacy')->table('tb_vessel_documentation')
            ->leftJoin('pl_vessel_document', 'tb_vessel_documentation.docID', '=', 'pl_vessel_document.vesDocID')
            ->leftJoin('pl_vessel_document_type', 'tb_vessel_documentation.vesDocTypeID', '=', 'pl_vessel_document_type.vesDocTypeID')
            ->where('pl_vessel_document_type.status', '1')
            ->where('pl_vessel_document.status', '1')
            ->where('tb_vessel_documentation.vesID', $vesselId)
            ->where('pl_vessel_document_type.is_deleted', '0')
            ->where('pl_vessel_document.is_deleted', '0')
            ->where('tb_vessel_documentation.is_deleted', '0')
            ->select([
                'tb_vessel_documentation.vessel_docID',
                'pl_vessel_document_type.document_type_name',
                'pl_vessel_document.document_name',
                'tb_vessel_documentation.doc_number',
                'tb_vessel_documentation.issuing_body',
                'tb_vessel_documentation.date_issued',
                'tb_vessel_documentation.date_expired',
                'tb_vessel_documentation.is_pf',
                'tb_vessel_documentation.status',
                DB::raw("{$warningCase} as warning_status"),
            ]);

        if ($typeId !== null) {
            $builder->where('pl_vessel_document.vesDocTypeID', $typeId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_vessel_documentation.doc_number', 'like', $term)
                    ->orWhere('tb_vessel_documentation.issuing_body', 'like', $term)
                    ->orWhere('pl_vessel_document.document_name', 'like', $term);
            });
        }

        $sortMap = [
            'doc_number' => 'tb_vessel_documentation.doc_number',
            'issuing_body' => 'tb_vessel_documentation.issuing_body',
            'date_issued' => 'tb_vessel_documentation.date_issued',
            'date_expired' => 'tb_vessel_documentation.date_expired',
            'is_active' => 'tb_vessel_documentation.status',
        ];

        if ($query->sort !== null && isset($sortMap[$query->sort])) {
            $builder->orderBy($sortMap[$query->sort], $query->direction);
        } else {
            $builder->orderByDesc(DB::raw('warning_status'))
                ->orderBy('pl_vessel_document_type.document_type_name')
                ->orderBy('pl_vessel_document.doc_ID');
        }

        $paginator = $builder->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => self::mapLegacyRow($r))->all();

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

    public function legacyDetail(string $vesselDocId): ?array
    {
        $numMonths = (int) (DB::connection('legacy')->table('tb_document_expiring')->where('expID', 1)->value('num_month') ?? 3);
        $warningCase = self::legacyWarningCaseSql('tb_vessel_documentation', $numMonths);

        $r = DB::connection('legacy')->table('tb_vessel_documentation')
            ->leftJoin('pl_vessel_document', 'tb_vessel_documentation.docID', '=', 'pl_vessel_document.vesDocID')
            ->leftJoin('pl_vessel_document_type', 'tb_vessel_documentation.vesDocTypeID', '=', 'pl_vessel_document_type.vesDocTypeID')
            ->where('tb_vessel_documentation.vessel_docID', $vesselDocId)
            ->select([
                'tb_vessel_documentation.vessel_docID',
                'tb_vessel_documentation.vesID',
                'tb_vessel_documentation.docID',
                'pl_vessel_document_type.document_type_name',
                'pl_vessel_document.document_name',
                'tb_vessel_documentation.doc_number',
                'tb_vessel_documentation.issuing_body',
                'tb_vessel_documentation.date_issued',
                'tb_vessel_documentation.date_expired',
                'tb_vessel_documentation.date_range_from',
                'tb_vessel_documentation.date_range_to',
                'tb_vessel_documentation.is_pf',
                'tb_vessel_documentation.status',
                'tb_vessel_documentation.shore_remarks',
                'tb_vessel_documentation.vessel_remarks',
                DB::raw("{$warningCase} as warning_status"),
            ])
            ->first();

        if ($r === null) {
            return null;
        }

        return [
            ...self::mapLegacyRow($r),
            'vessel_id' => $r->vesID,
            'vessel_document_id' => $r->docID,
            'date_range_from' => $r->date_range_from === '0000-00-00' ? null : $r->date_range_from,
            'date_range_to' => $r->date_range_to === '0000-00-00' ? null : $r->date_range_to,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ];
    }

    private static function mapLegacyRow(object $r): array
    {
        return [
            'id' => $r->vessel_docID,
            'document_type' => $r->document_type_name,
            'document' => $r->document_name,
            'doc_number' => $r->doc_number,
            'issuing_body' => $r->issuing_body,
            'date_issued' => $r->date_issued === '0000-00-00' ? null : $r->date_issued,
            'date_expired' => $r->date_expired === '0000-00-00' ? null : $r->date_expired,
            'is_printer_friendly' => $r->is_pf === '1',
            'warning_status' => (int) $r->warning_status,
            'is_active' => $r->status === '1',
            'can_edit' => false,
            'can_delete' => false,
        ];
    }

    /** Shared CASE expression for the expiring/expired warning status, ported from loadDocumentData()/loadData(). */
    private static function legacyWarningCaseSql(string $table, int $numMonths): string
    {
        return "(CASE
            WHEN {$table}.date_expired = '0000-00-00' THEN 0
            WHEN {$table}.date_expired <> '0000-00-00' AND {$table}.date_expired <= CURDATE() THEN 2
            WHEN {$table}.date_expired <> '0000-00-00' AND {$table}.date_expired > CURDATE() AND {$table}.date_range_from = '0000-00-00' AND CURDATE() >= DATE_SUB({$table}.date_expired, INTERVAL {$numMonths} MONTH) THEN 1
            WHEN {$table}.date_expired <> '0000-00-00' AND {$table}.date_expired > CURDATE() AND {$table}.date_range_from <> '0000-00-00' AND CURDATE() BETWEEN {$table}.date_range_from AND {$table}.date_range_to THEN 1
            ELSE 0
        END)";
    }
}
