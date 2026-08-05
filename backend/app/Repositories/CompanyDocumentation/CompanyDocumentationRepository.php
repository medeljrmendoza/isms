<?php

namespace App\Repositories\CompanyDocumentation;

use App\Models\CompanyDocumentation\CompanyDocument;
use App\Models\CompanyDocumentation\CompanyDocumentationRecord;
use App\Models\CompanyDocumentation\CompanyDocumentExpirySetting;
use App\Models\CompanyDocumentation\CompanyDocumentType;
use App\Repositories\ExposureHours\ExposureHoursRepository;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompanyDocumentationRepository
{
    private const COLUMNS = [
        ['key' => 'document', 'label' => 'DOCUMENT', 'sortable' => true],
        ['key' => 'date_issued', 'label' => 'ISSUED', 'sortable' => true],
        ['key' => 'date_expired', 'label' => 'EXPIRED', 'sortable' => true],
        ['key' => 'warning', 'label' => 'WARNING', 'sortable' => true],
    ];

    /**
     * The full module list's column set — see
     * Controllers/Company_documentation.php's loadData(). Not ported:
     * Page No./ID (print-layout fields) and ATTACHMENT/ARCHIVED (depend
     * on S3 upload + history-archive infra this migration doesn't
     * model) — same reasoning as VesselDocumentationRepository's
     * MODULE_COLUMNS.
     */
    private const MODULE_COLUMNS = [
        ['key' => 'document_type', 'label' => 'TYPE', 'sortable' => false],
        ['key' => 'document', 'label' => 'DOCUMENT', 'sortable' => false],
        ['key' => 'doc_number', 'label' => 'DOCUMENT NO.', 'sortable' => true],
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

    /**
     * Ported from Controllers/Dashboard_company_documentation.php's
     * loadCompanyDocsData(). The warning-status CASE expression there
     * relies on MySQL-specific date functions (CURDATE(), DATE_SUB())
     * that don't translate to portable SQL, so — same reasoning as
     * ExposureHoursRepository's latest-per-vessel computation — it's
     * computed here in PHP over the active/non-deleted records instead
     * of as a raw SQL expression.
     */
    public function pending(): Collection
    {
        $numMonths = CompanyDocumentExpirySetting::query()->value('num_month') ?? 3;

        return CompanyDocumentationRecord::query()
            ->with('companyDocument')
            ->whereHas('companyDocument', fn ($q) => $q->where('is_active', true))
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->get()
            ->each(function (CompanyDocumentationRecord $record) use ($numMonths) {
                $record->setAttribute('warning_status', $this->warningStatus($record, $numMonths));
            })
            ->filter(fn (CompanyDocumentationRecord $record) => $record->warning_status > 0)
            ->values();
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $records = $this->pending();

        if ($query->search !== null) {
            $term = mb_strtolower($query->search);
            $records = $records->filter(function (CompanyDocumentationRecord $record) use ($term) {
                return str_contains(mb_strtolower($record->companyDocument->name), $term)
                    || str_contains((string) $record->date_issued, $term)
                    || str_contains((string) $record->date_expired, $term);
            });
        }

        $sortable = [
            'document' => fn (CompanyDocumentationRecord $r) => mb_strtolower($r->companyDocument->name),
            'date_issued' => fn (CompanyDocumentationRecord $r) => (string) $r->date_issued,
            'date_expired' => fn (CompanyDocumentationRecord $r) => (string) $r->date_expired,
            'warning' => fn (CompanyDocumentationRecord $r) => $r->warning_status,
        ];
        $sortKey = $sortable[$query->sort ?? 'warning'] ?? $sortable['warning'];
        // Legacy's own default order is by warning status descending (expired before expiring soon).
        $descending = $query->sort === null ? true : $query->direction === 'desc';

        $sorted = $records->sortBy($sortKey, SORT_REGULAR, $descending)->values();

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
     * Ported from Controllers/Dashboard_company_documentation.php's
     * loadCompanyDocsData(). No vessel/user scoping — legacy's own query
     * has none for this dashlet (company documents aren't vessel-scoped).
     * The warning-status CASE expression runs against the real legacy
     * MySQL connection directly (unlike the local `pending()` path,
     * which has to reimplement it in PHP against SQLite).
     */
    public function legacyTable(TableQuery $query): array
    {
        $numMonths = (int) (DB::connection('legacy')->table('tb_company_document_expiring')->where('expID', 1)->value('num_month') ?? 3);

        $warningCase = "(CASE
            WHEN tb_company_documentation.date_expired = '0000-00-00' THEN 0
            WHEN tb_company_documentation.date_expired <> '0000-00-00' AND tb_company_documentation.date_expired <= CURDATE() THEN 2
            WHEN tb_company_documentation.date_expired <> '0000-00-00' AND tb_company_documentation.date_expired > CURDATE() AND tb_company_documentation.date_range_from = '0000-00-00' AND CURDATE() >= DATE_SUB(tb_company_documentation.date_expired, INTERVAL {$numMonths} MONTH) THEN 1
            WHEN tb_company_documentation.date_expired <> '0000-00-00' AND tb_company_documentation.date_expired > CURDATE() AND tb_company_documentation.date_range_from <> '0000-00-00' AND CURDATE() BETWEEN tb_company_documentation.date_range_from AND tb_company_documentation.date_range_to THEN 1
            ELSE 0
        END)";

        $builder = DB::connection('legacy')->table('tb_company_documentation')
            ->leftJoin('pl_company_document', 'tb_company_documentation.docID', '=', 'pl_company_document.vesDocID')
            ->leftJoin('pl_company_document_type', 'pl_company_document.vesDocTypeID', '=', 'pl_company_document_type.vesDocTypeID')
            ->where('pl_company_document_type.status', '1')
            ->where('pl_company_document_type.is_deleted', '0')
            ->where('pl_company_document.status', '1')
            ->where('pl_company_document.is_deleted', '0')
            ->where('tb_company_documentation.status', '1')
            ->where('tb_company_documentation.is_deleted', '0')
            ->whereRaw("(((tb_company_documentation.date_expired<>'0000-00-00') AND (tb_company_documentation.date_expired > CURDATE()) AND ((tb_company_documentation.date_range_from='0000-00-00' AND CURDATE() >= DATE_SUB(tb_company_documentation.date_expired, INTERVAL {$numMonths} MONTH)) OR (tb_company_documentation.date_range_from<>'0000-00-00' AND CURDATE() BETWEEN tb_company_documentation.date_range_from AND tb_company_documentation.date_range_to))) OR (tb_company_documentation.date_expired<>'0000-00-00' AND (tb_company_documentation.date_expired <= CURDATE())))")
            ->select([
                'pl_company_document.document_name',
                'tb_company_documentation.date_issued',
                'tb_company_documentation.date_expired',
                DB::raw("{$warningCase} as warning_status"),
            ]);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('pl_company_document.document_name', 'like', $term)
                    ->orWhere('tb_company_documentation.date_issued', 'like', $term)
                    ->orWhere('tb_company_documentation.date_expired', 'like', $term);
            });
        }

        $sortMap = [
            'document' => 'pl_company_document.document_name',
            'date_issued' => 'tb_company_documentation.date_issued',
            'date_expired' => 'tb_company_documentation.date_expired',
            'warning' => 'warning_status',
        ];
        $sort = $sortMap[$query->sort ?? 'warning'] ?? 'warning_status';
        // Legacy's own default order is by warning status descending (expired before expiring soon) — see table()'s equivalent local-path comment.
        $direction = $query->sort === null ? 'desc' : $query->direction;

        $paginator = $builder->orderBy($sort, $direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'document' => $r->document_name,
            'date_issued' => $r->date_issued,
            'date_expired' => $r->date_expired === '0000-00-00' ? 'Never' : $r->date_expired,
            'warning' => match ((int) $r->warning_status) {
                2 => 'EXPIRED',
                1 => 'EXPIRING SOON',
                default => '',
            },
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

    /** @return array<int, array{id:int,label:string}> */
    public function typeOptions(): array
    {
        return CompanyDocumentType::query()
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->orderBy('name')
            ->get()
            ->map(fn (CompanyDocumentType $t) => ['id' => $t->id, 'label' => $t->name])
            ->all();
    }

    /**
     * Active, non-deleted catalog documents — the Add form's Document
     * dropdown. Unlike VesselDocumentationRepository::catalogOptionsForVessel(),
     * this doesn't exclude documents that already have a record: company
     * documents naturally get reissued/renewed over time, and the
     * existing dashlet seed data already has multiple records per
     * catalog document (see CommitteeMeetingCompanyDocsSeeder), so
     * there's no 1-record-per-document invariant to enforce here.
     */
    public function catalogOptions(): array
    {
        return CompanyDocument::query()
            ->with('companyDocumentType')
            ->where('is_active', true)
            ->where('is_deleted', false)
            ->get()
            ->sortBy(fn (CompanyDocument $d) => $d->companyDocumentType->name.' — '.$d->name)
            ->map(fn (CompanyDocument $d) => ['id' => $d->id, 'label' => $d->companyDocumentType->name.' — '.$d->name])
            ->values()
            ->all();
    }

    /** Ported from loadData(). */
    public function fullTable(?int $typeId, TableQuery $query): LengthAwarePaginator
    {
        $numMonths = CompanyDocumentExpirySetting::query()->value('num_month') ?? 3;

        $builder = CompanyDocumentationRecord::query()
            ->with('companyDocument.companyDocumentType')
            ->where('is_deleted', false);

        if ($typeId !== null) {
            $builder->whereHas('companyDocument', fn (Builder $q) => $q->where('company_document_type_id', $typeId));
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('doc_number', 'like', $term)
                    ->orWhere('issuing_body', 'like', $term)
                    ->orWhereHas('companyDocument', fn (Builder $d) => $d->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_expired';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $paginator->getCollection()->each(function (CompanyDocumentationRecord $record) use ($numMonths) {
            $record->warning_status = $this->warningStatus($record, $numMonths);
        });

        return $paginator;
    }

    /**
     * Legacy's add_document() only ever updates a pre-existing
     * company_docID row — new catalog documents get a blank row created
     * elsewhere (presumably Company_document_list, not in this
     * migration). Since there's no such pre-provisioning step here, this
     * creates a genuine new record instead, matching
     * VesselDocumentationRepository::create()'s pattern.
     */
    public function create(array $data): CompanyDocumentationRecord
    {
        return CompanyDocumentationRecord::create([
            ...$data,
            'is_active' => true,
            'is_deleted' => false,
        ]);
    }

    /** company_document_id is frozen at creation time, same convention as VesselDocumentRecord. */
    public function update(CompanyDocumentationRecord $record, array $data): CompanyDocumentationRecord
    {
        unset($data['company_document_id']);

        $record->update($data);

        return $record;
    }

    /** Ported from stat_doc(): flips active/inactive. */
    public function toggleStatus(CompanyDocumentationRecord $record): CompanyDocumentationRecord
    {
        $record->update(['is_active' => ! $record->is_active]);

        return $record;
    }

    /** Ported from delete_company_documentation(): soft delete. */
    public function delete(CompanyDocumentationRecord $record): void
    {
        $record->update(['is_deleted' => true]);
    }

    /** 0 = fine, 1 = expiring soon, 2 = expired. */
    private function warningStatus(CompanyDocumentationRecord $record, int $numMonths): int
    {
        if ($record->date_expired === null) {
            return 0;
        }

        $today = Carbon::today();

        if ($record->date_expired->lte($today)) {
            return 2;
        }

        if ($record->date_range_from === null) {
            return $today->gte($record->date_expired->copy()->subMonths($numMonths)) ? 1 : 0;
        }

        return $today->between($record->date_range_from, $record->date_range_to) ? 1 : 0;
    }

    /**
     * Ported from Controllers/Company_documentation.php's index() (the
     * TYPE filter dropdown). pl_company_document_type carries a vesID
     * column but every row uses the same blank value in practice
     * (confirmed: single distinct vesID = "") — this module is genuinely
     * company-wide, matching loadData()'s own unscoped query and the
     * local typeOptions()'s docblock decision, so no vessel filter is
     * applied here either.
     */
    public function legacyTypeOptions(): array
    {
        return DB::connection('legacy')->table('pl_company_document_type')
            ->where('status', '1')
            ->where('is_deleted', '0')
            ->orderBy('document_type_name')
            ->get(['vesDocTypeID', 'document_type_name'])
            ->map(fn ($t) => ['id' => $t->vesDocTypeID, 'label' => $t->document_type_name])
            ->all();
    }

    /**
     * Active, non-deleted catalog documents — same "no existing-record
     * exclusion" decision as catalogOptions()'s docblock, applied
     * against legacy's own pl_company_document.
     */
    public function legacyDocumentOptions(): array
    {
        return DB::connection('legacy')->table('pl_company_document')
            ->where('status', '1')
            ->where('is_deleted', '0')
            ->orderBy('document_name')
            ->get(['vesDocID', 'document_name'])
            ->map(fn ($d) => ['id' => $d->vesDocID, 'label' => $d->document_name])
            ->all();
    }

    /**
     * Ported from loadData(). Legacy's own query has no
     * tb_company_documentation.status filter (unlike the vessel module's
     * loadDocumentData()) — both active and inactive records show, with
     * STATUS rendered as a badge — matching the local fullTable()'s own
     * `is_deleted`-only filter. Default sort replicates legacy's DataTable
     * fallback order (status_test DESC, document_type_name ASC, doc_ID
     * ASC) exactly, same reasoning as VesselDocumentationRepository's
     * legacyFullTable().
     */
    public function legacyFullTable(?string $typeId, TableQuery $query): array
    {
        $numMonths = (int) (DB::connection('legacy')->table('tb_company_document_expiring')->where('expID', 1)->value('num_month') ?? 3);
        $warningCase = self::legacyWarningCaseSql('tb_company_documentation', $numMonths);

        $builder = DB::connection('legacy')->table('tb_company_documentation')
            ->leftJoin('pl_company_document', 'tb_company_documentation.docID', '=', 'pl_company_document.vesDocID')
            ->leftJoin('pl_company_document_type', 'tb_company_documentation.vesDocTypeID', '=', 'pl_company_document_type.vesDocTypeID')
            ->where('pl_company_document_type.status', '1')
            ->where('pl_company_document_type.is_deleted', '0')
            ->where('pl_company_document.status', '1')
            ->where('pl_company_document.is_deleted', '0')
            ->where('tb_company_documentation.is_deleted', '0')
            ->select([
                'tb_company_documentation.company_docID',
                'pl_company_document_type.document_type_name',
                'pl_company_document.document_name',
                'tb_company_documentation.doc_number',
                'tb_company_documentation.issuing_body',
                'tb_company_documentation.date_issued',
                'tb_company_documentation.date_expired',
                'tb_company_documentation.is_pf',
                'tb_company_documentation.status',
                DB::raw("{$warningCase} as warning_status"),
            ]);

        if ($typeId !== null) {
            $builder->where('pl_company_document.vesDocTypeID', $typeId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_company_documentation.doc_number', 'like', $term)
                    ->orWhere('tb_company_documentation.issuing_body', 'like', $term)
                    ->orWhere('pl_company_document.document_name', 'like', $term);
            });
        }

        $sortMap = [
            'doc_number' => 'tb_company_documentation.doc_number',
            'issuing_body' => 'tb_company_documentation.issuing_body',
            'date_issued' => 'tb_company_documentation.date_issued',
            'date_expired' => 'tb_company_documentation.date_expired',
            'is_active' => 'tb_company_documentation.status',
        ];

        if ($query->sort !== null && isset($sortMap[$query->sort])) {
            $builder->orderBy($sortMap[$query->sort], $query->direction);
        } else {
            $builder->orderByDesc(DB::raw('warning_status'))
                ->orderBy('pl_company_document_type.document_type_name')
                ->orderBy('pl_company_document.doc_ID');
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

    public function legacyDetail(string $companyDocId): ?array
    {
        $numMonths = (int) (DB::connection('legacy')->table('tb_company_document_expiring')->where('expID', 1)->value('num_month') ?? 3);
        $warningCase = self::legacyWarningCaseSql('tb_company_documentation', $numMonths);

        $r = DB::connection('legacy')->table('tb_company_documentation')
            ->leftJoin('pl_company_document', 'tb_company_documentation.docID', '=', 'pl_company_document.vesDocID')
            ->leftJoin('pl_company_document_type', 'tb_company_documentation.vesDocTypeID', '=', 'pl_company_document_type.vesDocTypeID')
            ->where('tb_company_documentation.company_docID', $companyDocId)
            ->select([
                'tb_company_documentation.company_docID',
                'tb_company_documentation.docID',
                'pl_company_document_type.document_type_name',
                'pl_company_document.document_name',
                'tb_company_documentation.doc_number',
                'tb_company_documentation.issuing_body',
                'tb_company_documentation.date_issued',
                'tb_company_documentation.date_expired',
                'tb_company_documentation.date_range_from',
                'tb_company_documentation.date_range_to',
                'tb_company_documentation.is_pf',
                'tb_company_documentation.status',
                'tb_company_documentation.remarks',
                DB::raw("{$warningCase} as warning_status"),
            ])
            ->first();

        if ($r === null) {
            return null;
        }

        return [
            ...self::mapLegacyRow($r),
            'company_document_id' => $r->docID,
            'date_range_from' => $r->date_range_from === '0000-00-00' ? null : $r->date_range_from,
            'date_range_to' => $r->date_range_to === '0000-00-00' ? null : $r->date_range_to,
            'remarks' => $r->remarks,
        ];
    }

    private static function mapLegacyRow(object $r): array
    {
        return [
            'id' => $r->company_docID,
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
