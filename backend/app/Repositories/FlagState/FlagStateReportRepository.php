<?php

namespace App\Repositories\FlagState;

use App\Models\FlagState\FlagStateReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

class FlagStateReportRepository
{
    private const COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
    ];

    /**
     * The full module list's column set — see Controllers/Flag_state.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app, so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'added_by', 'label' => 'ADDED BY', 'sortable' => true],
        ['key' => 'dateof_inspection', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'inspector', 'label' => 'INSPECTOR', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PLACE OF INSPECTION', 'sortable' => true],
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
     * Ported from Controllers/Dashboard_flag_state.php's loadData() —
     * same missing-parens issue in the legacy WHERE clause as
     * ExternalAuditReportRepository (identical structure: an OBS branch
     * that would otherwise ignore vessel scoping and is_deleted), fixed
     * the same way with one properly grouped OR. See that repository's
     * docblock for the full explanation.
     */
    public function pendingQuery(): Builder
    {
        return FlagStateReport::query()
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
                    ->orWhere('dateof_inspection', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sortMap = ['date' => 'dateof_inspection'];
        $sort = in_array($query->sort, $sortable, true) ? ($sortMap[$query->sort] ?? $query->sort) : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from Controllers/Flag_state.php's loadData(). The
     * `WHERE vesID IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter.
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = FlagStateReport::query()->with('vessel')
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
                    ->orWhere('dateof_inspection', 'like', $term)
                    ->orWhere('inspector', 'like', $term)
                    ->orWhere('placeof_inspection', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /**
     * Ported from add_flag_state_report()'s insert branch: new records
     * are always SHORE-added (there's no VESSEL-origin path reachable
     * from this admin) and start unpublished/unapproved.
     */
    public function create(array $data): FlagStateReport
    {
        return FlagStateReport::create([
            ...$data,
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_flag_state_report()'s edit branch. Vessel and
     * added_by are frozen at creation time (legacy always re-reads them
     * from the existing row). is_published is left untouched — only the
     * separate publish toggle changes it — but is_approved
     * unconditionally resets to false on every save, published or not
     * (legacy hardcodes `"is_approved" => "0"` regardless of branch,
     * same as External Audits/SIRE/Non-SIRE). Legacy also has no ref_no
     * rename cascade into Nonconformities here — add_flag_state_report()
     * has no such UPDATE statement, matching External Audits.
     */
    public function update(FlagStateReport $report, array $data): FlagStateReport
    {
        unset($data['vessel_id']);

        $report->update([...$data, 'is_approved' => false]);

        return $report;
    }

    /**
     * Ported from publish_flag_state_report(): toggles is_published,
     * always sets is_approved true. Unlike External Audits' migrated
     * equivalent, this also cascades onto every currently-linked
     * Nonconformity row (matched by source_of_nc_ref_no, no is_inactive
     * filter — legacy's own SELECT has none): publishing/unpublishing
     * the parent report force-syncs each NC's is_published to match and
     * force-approves it. This is a real legacy behavior (nc_data resave
     * inside publish_flag_state_report()), not just the S3-file-sync
     * side effect it's bundled with — confirmed identical in
     * Controllers/External.php, where the migrated repository is
     * missing it (flagged separately for a follow-up fix).
     */
    public function publish(FlagStateReport $report): FlagStateReport
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
     * Ported from approve_flag_state_report(): sets is_approved true,
     * and — same as publish() above — force-approves every currently
     * linked Nonconformity row (is_published on those rows is left
     * untouched, matching legacy's `"is_published" => $key->is_published`).
     */
    public function approve(FlagStateReport $report): FlagStateReport
    {
        $report->update(['is_approved' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->ref_no)
            ->update(['is_approved' => true]);

        return $report;
    }

    /**
     * Ported from delete_flag_state_report(): soft delete, plus cascades
     * deactivating any Nonconformity rows linked by this report's ref.
     */
    public function delete(FlagStateReport $report): void
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
