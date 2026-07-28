<?php

namespace App\Repositories;

use App\Models\ExternalAuditReport;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class ExternalAuditReportRepository
{
    /** Same shape/caveats as AuditReportRepository — see its docblock. */
    private const COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /**
     * Ported from Controllers/Dashboard_external.php's loadData() —
     * intentionally NOT a literal port of the raw SQL string there.
     * That WHERE clause is built as
     * `vesID_scoped AND is_deleted='0' AND (A) OR (B)` with no
     * enclosing parens around `(A) OR (B)`. Because SQL's AND binds
     * tighter than OR, this parses as
     * `(vesID_scoped AND is_deleted='0' AND A) OR (B)` — the OBS branch
     * (B) ends up with no vessel scoping or deleted-filter at all. Given
     * A and B are identical except NC-vs-OBS, this reads as a
     * copy-paste-missing-parens bug, not intentional design, so this
     * implements the clearly-intended version instead: one properly
     * grouped OR covering the approval trigger and the pending-NC
     * check (Observations deferred, same as the other audit dashlets).
     *
     * The approval trigger itself is real, unlike the other
     * audit-style dashlets: a report can show up purely because it
     * still needs approval, even with zero pending non-conformities.
     */
    public function pendingQuery(): Builder
    {
        return ExternalAuditReport::query()
            ->with('vessel')
            ->where('is_deleted', false)
            ->where(function (Builder $query) {
                $query->where(function (Builder $shore) {
                    $shore->where('added_by', 'SHORE')
                        ->where('is_published', true)
                        ->where('is_approved', false);
                })->orWhere(function (Builder $vessel) {
                    $vessel->where('added_by', 'VESSEL')
                        ->where('is_approved', false);
                })->orWhereHas('nonconformities', function (Builder $nc) {
                    $nc->where('is_inactive', false)->whereNull('close_out_date');
                });
            })
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ]);
    }

    public function table(TableQuery $query): LengthAwarePaginator
    {
        $builder = $this->pendingQuery();

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('ref_no', 'like', $term)
                    ->orWhere('dateof_audit', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sortMap = ['date' => 'dateof_audit'];
        $sort = in_array($query->sort, $sortable, true) ? ($sortMap[$query->sort] ?? $query->sort) : 'dateof_audit';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }
}
