<?php

namespace App\Repositories\PscReports;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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

    /** Matches psc_report_v.php's 9-column DataTables layout (ACTIONS rendered client-side). */
    private const MODULE_COLUMNS = [
        ['key' => 'ref_no', 'label' => 'REF. NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'dateof_inspection', 'label' => 'DATE OF INSPECTION', 'sortable' => true],
        ['key' => 'placeof_inspection', 'label' => 'PORT OF INSPECTION', 'sortable' => false],
        ['key' => 'name_psco', 'label' => 'NAME OF INSPECTOR', 'sortable' => false],
        ['key' => 'mou', 'label' => 'PSC MOU', 'sortable' => false],
        ['key' => 'nc', 'label' => 'NC', 'sortable' => false],
        ['key' => 'obs', 'label' => 'OBS', 'sortable' => false],
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
     * logged-in user's assigned vessels. `obs` matches legacyTable()'s
     * existing pending/total tb_observations subqueries. can_edit is
     * unconditional (legacy's own Edit button visibility check is
     * commented out — see loadData()'s `pscreportid` column callback);
     * can_delete likewise, once user_level gating is dropped per the
     * no-roles-yet precedent. No can_reopen: legacy's Reopen button is
     * dead code — `reopen_psc_report()` exists as a controller action,
     * but the `$reopen` variable that would render its button is
     * entirely commented out of the callback's return, so it's never
     * actually reachable from this list.
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
        $pendingObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_psc_report.pscreportid')
            ->where('is_deleted', '!=', '1')->where('status', '!=', 'COMPLETED');
        $totalObsSub = fn ($q) => $q->from('tb_observations')->selectRaw('COUNT(*)')
            ->whereColumn('reportID', 'tb_psc_report.pscreportid')->where('is_deleted', '!=', '1');

        $builder = DB::connection('legacy')->table('tb_psc_report')
            ->leftJoin('tb_psc_mou', 'tb_psc_mou.mouID', '=', 'tb_psc_report.mouID')
            ->where('tb_psc_report.is_deleted', '0')
            ->whereIn('tb_psc_report.vesid', $assignedVesselIds)
            ->select([
                'tb_psc_report.pscreportid', 'tb_psc_report.ref_no', 'tb_psc_report.vesid', 'tb_psc_report.dateof_inspection',
                'tb_psc_report.placeof_inspection', 'tb_psc_report.name_psco', 'tb_psc_report.mou_others', 'tb_psc_mou.mou_name',
            ])
            ->selectSub($pendingNcSub, 'pending_nc')
            ->selectSub($totalNcSub, 'total_nc')
            ->selectSub($pendingObsSub, 'pending_obs')
            ->selectSub($totalObsSub, 'total_obs');

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
            'placeof_inspection' => $r->placeof_inspection,
            'name_psco' => $r->name_psco,
            'mou' => $r->mou_name === null ? null : ($r->mou_name === 'Others' ? "MOU - {$r->mou_others}" : $r->mou_name),
            'pending_nc_count' => $r->pending_nc,
            'total_nc_count' => $r->total_nc,
            'pending_obs_count' => $r->pending_obs,
            'total_obs_count' => $r->total_obs,
            'can_edit' => true,
            'can_delete' => true,
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
            'vessel_id' => $r->vesid !== '' ? $r->vesid : null,
            'placeof_inspection' => $r->placeof_inspection,
            'mou_id' => $r->mouID !== '' ? $r->mouID : null,
            'mou_others' => $r->mou_others,
            'name_psco' => $r->name_psco,
            'master_name' => $r->master_id,
            'chief_engineer' => $r->chief_engineer,
            'is_detained' => self::isFlagSet($r->is_detained),
            'detained_date' => $zeroDateToNull($r->detained_date),
            'detained_time' => $r->detained_time,
            'is_released' => self::isFlagSet($r->is_released),
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
            'can_edit' => true,
            'can_delete' => true,
            'vessel_id' => $r['vessel_id'],
            'placeof_inspection' => $r['placeof_inspection'],
            'mou_id' => $r['mou_id'],
            'mou_others' => $r['mou_others'],
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

    /**
     * tb_psc_report's is_detained/is_released/is_deleted flag columns
     * are real SQL `int` columns, and this connection's PDO returns
     * native-typed ints for them, so comparing against the string '1'
     * silently and always fails — see the identical fix in
     * NonconformityRepository.
     */
    private static function isFlagSet(mixed $value): bool
    {
        return (string) $value === '1';
    }

    /**
     * Ported from add_psc_report(): create (pscreportid empty) and edit
     * share one save, but unlike Nonconformities/Incident Report this is
     * a plain UPDATE on edit, not a delete-then-reinsert — legacy calls
     * `saveRecord($data, $new_pscID)` here, not a raw DELETE+INSERT.
     * Duplicate ref_no (scoped to non-deleted reports, excluding this
     * row on edit) blocks the save exactly like legacy's own
     * `count_exist_ref_no` check. Editing a report also cascades a
     * changed ref_no into any linked Nonconformity rows, so those don't
     * get orphaned — matches legacy's `UPDATE tb_nonconformities SET
     * source_of_nc_ref_no=...` on save. Not ported: file attachment
     * upload and the tb_logs audit trail (no infra for either anywhere
     * in this migration) and the SIRE-book linkage fields (bookID) —
     * only reachable when Add is launched from within a SIRE book, a
     * flow this migration doesn't model, matching the standing "no
     * unreachable triggers" precedent.
     */
    public function legacySave(?string $pscreportid, array $data): array
    {
        $legacy = DB::connection('legacy');
        $isEdit = $pscreportid !== null;
        $newId = $pscreportid ?? ('psc'.uniqid());
        $existing = $isEdit ? $legacy->table('tb_psc_report')->where('pscreportid', $newId)->first() : null;

        if ($isEdit && $existing === null) {
            abort(404);
        }

        $duplicate = $legacy->table('tb_psc_report')
            ->where('ref_no', $data['ref_no'])
            ->where('is_deleted', '0')
            ->when($isEdit, fn ($q) => $q->where('pscreportid', '!=', $newId))
            ->exists();
        if ($duplicate) {
            throw ValidationException::withMessages(['ref_no' => 'This Reference No. already exists.']);
        }

        $data = $this->clearInapplicableFields($data);
        $vesid = $isEdit ? $existing->vesid : $data['vessel_id'];

        $row = [
            'pscreportid' => $newId,
            'ref_no' => $data['ref_no'],
            'vesid' => $vesid,
            'dateof_inspection' => $data['dateof_inspection'],
            'placeof_inspection' => $data['placeof_inspection'],
            'mouID' => $data['mou_id'] ?? '',
            'mou_others' => $data['mou_others'] ?? '',
            'name_psco' => $data['name_psco'] ?? '',
            'master_id' => $data['master_name'] ?? '',
            'chief_engineer' => $data['chief_engineer'] ?? '',
            'is_detained' => $data['is_detained'] ?? '0',
            'detained_date' => $data['detained_date'] ?? '',
            'detained_time' => $data['detained_time'] ?? '',
            'is_released' => $data['is_released'] ?? '0',
            'released_date' => $data['released_date'] ?? '',
            'released_time' => $data['released_time'] ?? '',
            'closing_date' => $data['closing_date'] ?? '',
            'remarks' => $data['remarks'] ?? '',
        ];

        if ($isEdit) {
            $legacy->table('tb_psc_report')->where('pscreportid', $newId)->update($row);

            if ($data['ref_no'] !== $existing->ref_no) {
                $legacy->table('tb_nonconformities')
                    ->where('source_of_nc_ref_no', $existing->ref_no)
                    ->update(['source_of_nc_ref_no' => $data['ref_no']]);
            }
        } else {
            $row['is_deleted'] = '0';
            $legacy->table('tb_psc_report')->insert($row);
        }

        return $this->legacyDetail($newId);
    }

    /** Ported from delete_psc_report(): soft delete (is_deleted=1, not a real DELETE — legacy's own hard-delete queries are commented out), plus cascades deactivating any linked Nonconformity rows. */
    public function legacyDelete(string $pscreportid): void
    {
        $legacy = DB::connection('legacy');
        $r = $legacy->table('tb_psc_report')->where('pscreportid', $pscreportid)->first();
        abort_if($r === null, 404);

        $legacy->table('tb_psc_report')->where('pscreportid', $pscreportid)->update(['is_deleted' => '1']);
        $legacy->table('tb_nonconformities')->where('source_of_nc_ref_no', $r->ref_no)->update(['is_inactive' => '1']);
    }

    /** Ported from add_psc_report(): clears detained/released fields that don't apply given the current flags — same shape as Nonconformities'/Incident Report's clearing helpers. */
    private function clearInapplicableFields(array $data): array
    {
        if (empty($data['is_detained'])) {
            $data['detained_date'] = '';
            $data['detained_time'] = '';
            $data['is_released'] = '0';
            $data['released_date'] = '';
            $data['released_time'] = '';
        } elseif (empty($data['is_released'])) {
            $data['released_date'] = '';
            $data['released_time'] = '';
        }

        return $data;
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(?string $legacyUserId): array
    {
        return LegacyDb::assignedVesselOptions($legacyUserId);
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyMouOptions(): array
    {
        return DB::connection('legacy')->table('tb_psc_mou')->orderBy('mou_name')->get()
            ->map(fn ($m) => ['id' => $m->mouID, 'label' => $m->mou_name])->all();
    }
}
