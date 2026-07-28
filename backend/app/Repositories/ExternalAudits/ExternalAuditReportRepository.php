<?php

namespace App\Repositories\ExternalAudits;

use App\Models\ExternalAudits\ExternalAuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
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

    /**
     * The full module list's column set — see Controllers/External.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_audit', 'label' => 'DATE OF AUDIT', 'sortable' => true],
        ['key' => 'portof_audit', 'label' => 'PORT OF AUDIT', 'sortable' => true],
        ['key' => 'typeof_audit', 'label' => 'TYPE', 'sortable' => true],
        ['key' => 'published', 'label' => 'PUBLISHED', 'sortable' => false],
        ['key' => 'is_approved', 'label' => 'APPROVED', 'sortable' => false],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
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

    /**
     * Ported from Controllers/External.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = ExternalAuditReport::query()->with('vessel')
            ->where('is_deleted', false)
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ]);

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('vessel_id', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('ref_no', 'like', $term)
                    ->orWhere('dateof_audit', 'like', $term)
                    ->orWhere('portof_audit', 'like', $term)
                    ->orWhere('typeof_audit', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_audit';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_external_report()'s insert branch: new records
     * are always SHORE-added (there's no VESSEL-origin path reachable
     * from this admin — those rows only ever arrive via the unmigrated
     * vessel-side app) and start unpublished/unapproved.
     */
    public function create(array $data): ExternalAuditReport
    {
        return ExternalAuditReport::create([
            ...$data,
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_external_report()'s edit branch. Vessel and
     * added_by are frozen at creation time (legacy always re-reads them
     * from the existing row). is_published is left untouched — it's
     * only ever changed via the separate publish toggle — but
     * is_approved unconditionally resets to false on every save,
     * published or not (legacy hardcodes `"is_approved" => "0"`
     * regardless of branch). Unlike Company/PSC/Internal, legacy does
     * NOT cascade a ref_no change into linked Nonconformities here —
     * add_external_report() has no such UPDATE statement, so this
     * doesn't add one either.
     */
    public function update(ExternalAuditReport $report, array $data): ExternalAuditReport
    {
        unset($data['vessel_id']);

        $report->update([...$data, 'is_approved' => false]);

        return $report;
    }

    /**
     * Ported from publish_external_report(): toggles is_published,
     * always sets is_approved true. Also cascades onto every currently-
     * linked Nonconformity row (matched by source_of_nc_ref_no, no
     * is_inactive filter — legacy's own SELECT has none): publishing/
     * unpublishing the parent report force-syncs each NC's is_published
     * to match and force-approves it. This is a real legacy behavior
     * (the nc_data resave inside publish_external_report()), not just
     * the S3-file-sync side effect it's bundled with — see
     * FlagStateReportRepository::publish() for the same cascade, ported
     * there first.
     */
    public function publish(ExternalAuditReport $report): ExternalAuditReport
    {
        $report->update([
            'is_published' => ! $report->is_published,
            'is_approved' => true,
        ]);

        Nonconformity::where('source_of_nc_ref_no', $report->ref_no)
            ->update(['is_published' => $report->is_published, 'is_approved' => true]);

        return $report;
    }

    /**
     * Ported from approve_external_report(): sets is_approved true, and —
     * same as publish() above — force-approves every currently linked
     * Nonconformity row (is_published on those rows is left untouched,
     * matching legacy's `"is_published" => $key->is_published`).
     */
    public function approve(ExternalAuditReport $report): ExternalAuditReport
    {
        $report->update(['is_approved' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->ref_no)
            ->update(['is_approved' => true]);

        return $report;
    }

    /**
     * Ported from delete_external_report(): soft delete, plus cascades
     * deactivating any Nonconformity rows linked by this report's ref.
     */
    public function delete(ExternalAuditReport $report): void
    {
        $report->update(['is_deleted' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->ref_no)->update(['is_inactive' => true]);
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }
}
