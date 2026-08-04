<?php

namespace App\Repositories\ExternalAudits;

use App\Models\ExternalAudits\ExternalAuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
     * Ported from Controllers/Dashboard_external.php's loadData() — same
     * shape as FlagStateReportRepository::legacyTable() (visible when
     * still-needs-approval OR has a pending NC OR has a pending
     * observation), using the same "clearly intended" 3-way-OR reading
     * rather than the literal missing-parens bug — see that method's
     * docblock and this class's `pendingQuery()` docblock.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_external_audit_report.ref_no')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_external_audit_report.ref_no')
            ->where('is_inactive', '!=', '1');
        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_external_audit_report.externalID')
            ->where('status', '!=', 'COMPLETED')->where('is_deleted', '0');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_external_audit_report.externalID')->where('is_deleted', '0');

        $needsApproval = function ($q) {
            $q->where(function ($shore) {
                $shore->where('added_by', 'SHORE')->where('is_published', '1')->where('is_approved', '0');
            })->orWhere(function ($vessel) {
                $vessel->where('added_by', 'VESSEL')->where('is_approved', '0');
            });
        };

        $builder = DB::connection('legacy')->table('tb_external_audit_report')
            ->where('is_deleted', '0')
            ->whereIn('vesID', $assignedVesselIds)
            ->where(function ($q) use ($needsApproval) {
                $needsApproval($q);
                $q->orWhereIn('ref_no', function ($sub) {
                    $sub->select('source_of_nc_ref_no')->from('tb_nonconformities')
                        ->where('is_inactive', '!=', '1')
                        ->where(function ($qq) {
                            $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
                        });
                })->orWhereIn('externalID', function ($sub) {
                    $sub->select('reportID')->from('tb_observations')
                        ->where('is_deleted', '0')->where('status', '!=', 'COMPLETED');
                });
            })
            ->select(['tb_external_audit_report.externalID', 'tb_external_audit_report.ref_no', 'tb_external_audit_report.vesID', 'tb_external_audit_report.dateof_audit'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc')
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_external_audit_report.ref_no', 'like', $term)
                    ->orWhere('tb_external_audit_report.dateof_audit', 'like', $term);
            });
        }

        $sortMap = ['ref_no' => 'tb_external_audit_report.ref_no', 'date' => 'tb_external_audit_report.dateof_audit'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_external_audit_report.dateof_audit';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->externalID,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'date' => $r->dateof_audit,
            'nc' => $r->total_nc > 0 ? "{$r->pending_nc}/{$r->total_nc}" : '',
            'obs' => $r->total_obs > 0 ? "{$r->pending_obs}/{$r->total_obs}" : '',
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

    /**
     * Ported from admin/external/view_external.php, surfaced via the
     * dashboard's clickable ref_no column. Read-only — see
     * SireReportRepository::detail()'s docblock for the convention.
     */
    public function detail(int $id): ?array
    {
        $r = ExternalAuditReport::query()->with('vessel')
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ])
            ->find($id);

        if ($r === null) {
            return null;
        }

        return $this->toDetailArray([
            'ref_no' => $r->ref_no,
            'vessel' => $r->vessel?->display_name ?? '',
            'added_by' => $r->added_by,
            'dateof_audit' => $r->dateof_audit->format('Y-m-d'),
            'portof_audit' => $r->portof_audit,
            'typeof_audit' => $r->typeof_audit,
            'published' => $r->added_by === 'SHORE' ? $r->is_published : null,
            'is_approved' => $r->is_approved,
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'department' => $r->department,
            'master_name' => $r->master_name,
            'chief_engineer' => $r->chief_engineer,
            'auditor_name' => $r->auditor_name,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ]);
    }

    /** Same as detail(), reading tb_external_audit_report directly from the legacy connection. */
    public function legacyDetail(string $externalID): ?array
    {
        $r = DB::connection('legacy')->table('tb_external_audit_report')->where('externalID', $externalID)->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();

        $pendingNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)->where('is_inactive', '!=', '1')
            ->where(function ($q) {
                $q->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            })->count();
        $totalNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)->where('is_inactive', '!=', '1')->count();

        return $this->toDetailArray([
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesID] ?? '',
            'added_by' => $r->added_by,
            'dateof_audit' => $r->dateof_audit,
            'portof_audit' => $r->portof_audit,
            'typeof_audit' => $r->typeof_audit,
            'published' => $r->added_by === 'SHORE' ? $r->is_published === '1' : null,
            'is_approved' => $r->is_approved === '1',
            'pending_nc_count' => $pendingNc,
            'total_nc_count' => $totalNc,
            'department' => $r->department,
            'master_name' => $r->master,
            'chief_engineer' => $r->chief_engineer,
            'auditor_name' => LegacyDb::addressBookEntry($r->auditor)['name'] ?? $r->auditor,
            'shore_remarks' => $r->shore_remarks,
            'vessel_remarks' => $r->vessel_remarks,
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => 0,
            'ref_no' => $r['ref_no'],
            'vessel' => $r['vessel'],
            'added_by' => $r['added_by'],
            'dateof_audit' => $r['dateof_audit'],
            'portof_audit' => $r['portof_audit'],
            'typeof_audit' => $r['typeof_audit'],
            'published' => $r['published'],
            'is_approved' => $r['is_approved'],
            'pending_nc_count' => $r['pending_nc_count'],
            'total_nc_count' => $r['total_nc_count'],
            'can_edit' => false,
            'can_publish' => false,
            'can_approve' => false,
            'can_delete' => false,
            'vessel_id' => null,
            'department' => $r['department'],
            'master_name' => $r['master_name'],
            'chief_engineer' => $r['chief_engineer'],
            'auditor_name' => $r['auditor_name'],
            'shore_remarks' => $r['shore_remarks'],
            'vessel_remarks' => $r['vessel_remarks'],
        ];
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }
}
