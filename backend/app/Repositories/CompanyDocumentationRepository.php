<?php

namespace App\Repositories;

use App\Models\CompanyDocumentationRecord;
use App\Models\CompanyDocumentExpirySetting;
use App\Support\TableQuery;
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

    public static function columns(): array
    {
        return self::COLUMNS;
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
