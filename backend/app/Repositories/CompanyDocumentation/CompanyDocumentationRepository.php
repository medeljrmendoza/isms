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
}
