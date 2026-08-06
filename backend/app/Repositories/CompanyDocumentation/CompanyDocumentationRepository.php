<?php

namespace App\Repositories\CompanyDocumentation;

use App\Support\TableQuery;
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
     * loadCompanyDocsData(). No vessel/user scoping — legacy's own query
     * has none for this dashlet (company documents aren't vessel-scoped).
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
        // Legacy's own default order is by warning status descending (expired before expiring soon).
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

    /**
     * Ported from Controllers/Company_documentation.php's index() (the
     * TYPE filter dropdown). pl_company_document_type carries a vesID
     * column but every row uses the same blank value in practice
     * (confirmed: single distinct vesID = "") — this module is genuinely
     * company-wide, matching loadData()'s own unscoped query, so no
     * vessel filter is applied here either.
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

    /** Active, non-deleted catalog documents, from legacy's pl_company_document. */
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
     * STATUS rendered as a badge. Default sort replicates legacy's DataTable
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
