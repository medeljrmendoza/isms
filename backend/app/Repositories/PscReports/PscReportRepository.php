<?php

namespace App\Repositories\PscReports;

use App\Support\LegacyDb;
use App\Support\TableQuery;
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
     * Ported from Controllers/Psc.php's loadData(), reading
     * tb_psc_report directly from the legacy connection, scoped to the
     * logged-in user's assigned vessels. Read-only: can_edit/can_delete/
     * can_reopen are always false, since there's no legacy write path.
     */
    public function legacyFullTable(TableQuery $query, ?string $vesselId, ?string $legacyUserId): array
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

        $builder = DB::connection('legacy')->table('tb_psc_report')
            ->leftJoin('tb_psc_mou', 'tb_psc_mou.mouID', '=', 'tb_psc_report.mouID')
            ->where('tb_psc_report.is_deleted', '0')
            ->whereIn('tb_psc_report.vesid', $assignedVesselIds)
            ->select(['tb_psc_report.pscreportid', 'tb_psc_report.ref_no', 'tb_psc_report.vesid', 'tb_psc_report.dateof_inspection', 'tb_psc_report.mou_others', 'tb_psc_mou.mou_name'])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc');

        if ($vesselId !== null && $vesselId !== '' && $vesselId !== 'ALL') {
            $builder->where('tb_psc_report.vesid', $vesselId);
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('tb_psc_report.ref_no', 'like', $term)
                    ->orWhere('tb_psc_report.placeof_inspection', 'like', $term);
            });
        }

        $sortMap = ['ref_no' => 'tb_psc_report.ref_no', 'date' => 'tb_psc_report.dateof_inspection'];
        $sort = $sortMap[$query->sort ?? ''] ?? 'tb_psc_report.dateof_inspection';

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($r) => [
            'id' => $r->pscreportid,
            'ref_no' => $r->ref_no,
            'vessel' => $vessels[$r->vesid] ?? '',
            'dateof_inspection' => $r->dateof_inspection,
            'mou' => $r->mou_name === null ? null : ($r->mou_name === 'Others' ? "MOU - {$r->mou_others}" : $r->mou_name),
            'pending_nc_count' => $r->pending_nc,
            'total_nc_count' => $r->total_nc,
            'can_edit' => false,
            'can_delete' => false,
            'can_reopen' => false,
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

    /** Ported from admin/psc/view_psc.php, reading tb_psc_report directly from the legacy connection. */
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
            'id' => $r->pscreportid,
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
            'id' => $r['id'],
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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }
}
