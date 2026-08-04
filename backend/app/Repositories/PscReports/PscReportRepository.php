<?php

namespace App\Repositories\PscReports;

use App\Models\Nonconformities\Nonconformity;
use App\Models\PscReports\PscMouAuthority;
use App\Models\PscReports\PscReport;
use App\Models\Vessel;
use App\Repositories\CompanyInspections\AuditReportRepository;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class PscReportRepository
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
     * The full module list's column set — see Controllers/Psc.php's
     * loadData(). "obs" is dropped: the Observations module doesn't
     * exist in this app (see conversation notes on the standing
     * decision), so that column would always read "—".
     */
    private const MODULE_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'date', 'label' => 'DATE OF INSPECTION', 'sortable' => true],
        ['key' => 'mou', 'label' => 'MOU', 'sortable' => false],
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
     * Ported from Controllers/Dashboard_psc.php's loadData(). Same
     * Nonconformities-only simplification as AuditReportRepository.
     */
    public function pendingQuery(): Builder
    {
        return PscReport::query()
            ->with('vessel')
            ->where('is_deleted', false)
            ->whereHas('nonconformities', function (Builder $q) {
                $q->where('is_inactive', false)->whereNull('close_out_date');
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
     * Ported from Controllers/Dashboard_psc.php's loadData(): visible
     * when there's at least one pending NC or pending observation,
     * scoped to the logged-in user's assigned vessels. Both real counts
     * are implemented here (unlike the local `pendingQuery()`, which
     * only has Nonconformities to check against).
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $assignedVesselIds = LegacyDb::assignedVesselIds($legacyUserId);

        $pendingNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_psc_report.ref_no')
            ->where('is_inactive', '!=', '1')
            ->where(function ($qq) {
                $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            });
        $totalNcSub = fn ($q) => $q->from('tb_nonconformities')->selectRaw('COUNT(*)')
            ->whereColumn('source_of_nc_ref_no', 'tb_psc_report.ref_no')
            ->where('is_inactive', '!=', '1');
        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_psc_report.pscreportid')
            ->where('is_deleted', '!=', '1')->where('status', '!=', 'COMPLETED');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_psc_report.pscreportid')->where('is_deleted', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_psc_report')
            ->where('is_deleted', '0')
            ->whereIn('vesid', $assignedVesselIds)
            ->where(function ($q) {
                $q->whereIn('ref_no', function ($sub) {
                    $sub->select('source_of_nc_ref_no')->from('tb_nonconformities')
                        ->where('is_inactive', '!=', '1')
                        ->where(function ($qq) {
                            $qq->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
                        });
                })->orWhereIn('pscreportid', function ($sub) {
                    $sub->select('reportID')->from('tb_observations')
                        ->where('is_deleted', '!=', '1')->where('status', '!=', 'COMPLETED');
                });
            })
            ->select(['tb_psc_report.pscreportid', 'tb_psc_report.ref_no', 'tb_psc_report.vesid', 'tb_psc_report.dateof_inspection'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc')
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_psc_report.ref_no', 'like', $term)
                    ->orWhere('tb_psc_report.dateof_inspection', 'like', $term);
            });
        }

        $sortMap = ['ref_no' => 'tb_psc_report.ref_no', 'date' => 'tb_psc_report.dateof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_psc_report.dateof_inspection';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'record_id' => $r->pscreportid,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesid] ?? '',
            'date' => $r->dateof_inspection,
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
     * Ported from Controllers/Psc.php's loadData(). The
     * `WHERE vesid IN (SELECT ... tb_user_vessel)` scoping is dropped
     * like everywhere else; the vessel filter is kept since it's a
     * genuine user-facing filter (legacy's optional vesID URL segment).
     */
    public function fullTable(TableQuery $query, ?string $vesselId): LengthAwarePaginator
    {
        $builder = PscReport::query()->with(['vessel', 'mou'])
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
                    ->orWhere('placeof_inspection', 'like', $term)
                    ->orWhereHas('vessel', fn (Builder $v) => $v->where('name', 'like', $term));
            });
        }

        $sortable = array_column(array_filter(self::MODULE_COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sortMap = ['date' => 'dateof_inspection'];
        $sort = in_array($query->sort, $sortable, true) ? ($sortMap[$query->sort] ?? $query->sort) : 'dateof_inspection';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    /** Ported from add_psc_report()'s insert branch. */
    public function create(array $data): PscReport
    {
        $data = $this->clearInapplicableFields($data);

        return PscReport::create([
            ...$data,
            'is_deleted' => false,
        ]);
    }

    /**
     * Ported from add_psc_report()'s update branch: when ref_no changes,
     * cascades the new value into any Nonconformity rows linked by the
     * old ref_no (loose string-key relation — see PscReport::nonconformities()),
     * so those NCs don't get orphaned.
     */
    public function update(PscReport $report, array $data): PscReport
    {
        $data = $this->clearInapplicableFields($data);

        $oldRefNo = $report->ref_no;
        unset($data['vessel_id']);

        $report->update($data);

        if ($data['ref_no'] !== $oldRefNo) {
            Nonconformity::where('source_of_nc_ref_no', $oldRefNo)->update(['source_of_nc_ref_no' => $data['ref_no']]);
        }

        return $report;
    }

    /** Ported from reopen_psc_report(): simple clear, no other side effects. */
    public function reopen(PscReport $report): PscReport
    {
        $report->update(['closing_date' => null]);

        return $report;
    }

    /**
     * Ported from delete_psc_report(): soft delete, plus cascades
     * deactivating any Nonconformity rows linked by this report's ref_no.
     */
    public function delete(PscReport $report): void
    {
        $report->update(['is_deleted' => true]);

        Nonconformity::where('source_of_nc_ref_no', $report->ref_no)->update(['is_inactive' => true]);
    }

    /** Clears detained/released fields that don't apply given the current flags. */
    private function clearInapplicableFields(array $data): array
    {
        if (empty($data['is_detained'])) {
            $data['detained_date'] = null;
            $data['detained_time'] = null;
            $data['is_released'] = false;
            $data['released_date'] = null;
            $data['released_time'] = null;
        } elseif (empty($data['is_released'])) {
            $data['released_date'] = null;
            $data['released_time'] = null;
        }

        return $data;
    }

    /**
     * Ported from admin/psc/view_psc.php, surfaced via the dashboard's
     * clickable ref_no column. Read-only — see
     * SireReportRepository::detail()'s docblock for the convention.
     */
    public function detail(int $id): ?array
    {
        $r = PscReport::query()->with(['vessel', 'mou'])
            ->withCount([
                'nonconformities as pending_nc_count' => fn (Builder $q) => $q->where('is_inactive', false)->whereNull('close_out_date'),
                'nonconformities as total_nc_count' => fn (Builder $q) => $q->where('is_inactive', false),
            ])
            ->find($id);

        if ($r === null) {
            return null;
        }

        $mouName = $r->mou === null ? null : ($r->mou->name === 'Others' ? "MOU - {$r->mou_others}" : $r->mou->name);

        return $this->toDetailArray([
            'ref_no' => $r->ref_no,
            'vessel' => $r->vessel?->display_name ?? '',
            'dateof_inspection' => $r->dateof_inspection->format('Y-m-d'),
            'mou' => $mouName,
            'pending_nc_count' => $r->pending_nc_count ?? 0,
            'total_nc_count' => $r->total_nc_count ?? 0,
            'placeof_inspection' => $r->placeof_inspection,
            'name_psco' => $r->name_psco,
            'master_name' => $r->master_name,
            'chief_engineer' => $r->chief_engineer,
            'is_detained' => $r->is_detained,
            'detained_date' => $r->detained_date?->format('Y-m-d'),
            'detained_time' => $r->detained_time,
            'is_released' => $r->is_released,
            'released_date' => $r->released_date?->format('Y-m-d'),
            'released_time' => $r->released_time,
            'closing_date' => $r->closing_date?->format('Y-m-d'),
            'remarks' => $r->remarks,
        ]);
    }

    /** Same as detail(), reading tb_psc_report directly from the legacy connection. */
    public function legacyDetail(string $pscreportid): ?array
    {
        $r = DB::connection('legacy')->table('tb_psc_report')
            ->leftJoin('tb_psc_mou', 'tb_psc_mou.mouID', '=', 'tb_psc_report.mouID')
            ->where('tb_psc_report.pscreportid', $pscreportid)
            ->select(['tb_psc_report.*', 'tb_psc_mou.mou_name'])
            ->first();

        if ($r === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;
        $mouName = $r->mou_name === null ? null : ($r->mou_name === 'Others' ? "MOU - {$r->mou_others}" : $r->mou_name);

        $pendingNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)->where('is_inactive', '!=', '1')
            ->where(function ($q) {
                $q->whereNull('close_out_date')->orWhere('close_out_date', '0000-00-00');
            })->count();
        $totalNc = DB::connection('legacy')->table('tb_nonconformities')
            ->where('source_of_nc_ref_no', $r->ref_no)->where('is_inactive', '!=', '1')->count();

        return $this->toDetailArray([
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesid] ?? '',
            'dateof_inspection' => $r->dateof_inspection,
            'mou' => $mouName,
            'pending_nc_count' => $pendingNc,
            'total_nc_count' => $totalNc,
            'placeof_inspection' => $r->placeof_inspection,
            'name_psco' => $r->name_psco,
            'master_name' => $r->master_id,
            'chief_engineer' => $r->chief_engineer,
            'is_detained' => $r->is_detained === '1',
            'detained_date' => $zeroDateToNull($r->detained_date),
            'detained_time' => $r->detained_time,
            'is_released' => $r->is_released === '1',
            'released_date' => $zeroDateToNull($r->released_date),
            'released_time' => $r->released_time,
            'closing_date' => $zeroDateToNull($r->closing_date),
            'remarks' => $r->remarks,
        ]);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r): array
    {
        return [
            'id' => 0,
            'ref_no' => $r['ref_no'],
            'vessel' => $r['vessel'],
            'dateof_inspection' => $r['dateof_inspection'],
            'mou' => $r['mou'],
            'pending_nc_count' => $r['pending_nc_count'],
            'total_nc_count' => $r['total_nc_count'],
            'can_edit' => false,
            'can_delete' => false,
            'can_reopen' => false,
            'vessel_id' => null,
            'placeof_inspection' => $r['placeof_inspection'],
            'mou_id' => null,
            'mou_others' => null,
            'name_psco' => $r['name_psco'],
            'master_name' => $r['master_name'],
            'chief_engineer' => $r['chief_engineer'],
            'is_detained' => $r['is_detained'],
            'detained_date' => $r['detained_date'],
            'detained_time' => $r['detained_time'],
            'is_released' => $r['is_released'],
            'released_date' => $r['released_date'],
            'released_time' => $r['released_time'],
            'closing_date' => $r['closing_date'],
            'remarks' => $r['remarks'],
        ];
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function mouOptions(): array
    {
        return PscMouAuthority::query()->orderBy('name')->get()
            ->map(fn (PscMouAuthority $m) => ['id' => $m->id, 'label' => $m->name])
            ->all();
    }
}
