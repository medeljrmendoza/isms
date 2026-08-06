<?php

namespace App\Repositories\Defects;

use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Support\Facades\DB;

/** Ported from Controllers/Defect_list.php. */
class DefectRepository
{
    private const COLUMNS = [
        ['key' => 'sl_no', 'label' => 'SL NO.', 'sortable' => true],
        ['key' => 'vessel', 'label' => 'VESSEL', 'sortable' => false],
        ['key' => 'defect_date', 'label' => 'DATE', 'sortable' => true],
        ['key' => 'priority', 'label' => 'PRIORITY', 'sortable' => true],
        ['key' => 'category', 'label' => 'CATEGORY', 'sortable' => true],
        ['key' => 'compl_code', 'label' => 'COMPL CODE', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyVesselOptions(): array
    {
        return collect(LegacyDb::vesselNames())
            ->map(fn ($name, $id) => ['id' => $id, 'label' => $name])
            ->values()
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * Ported from Controllers/Dashboard_defect_list.php's loadData().
     * Legacy also scopes tb_defect_list.vesID to the user's assigned
     * vessels and requires tb_vessel.vessel_status='ACTIVE' via a join —
     * vessel/user scoping is deferred everywhere in this migration, so
     * only the compl_code exclusion (not yet marked Complete) remains.
     */
    public function legacyTable(TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $eligibleVesselIds = LegacyDb::assignedVesselIds($legacyUserId)->intersect(LegacyDb::activeVesselIds());

        $builder = DB::connection('legacy')->table('tb_defect_list')
            ->where('compl_code', '!=', 'C')
            ->whereIn('vesID', $eligibleVesselIds);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('sl_no', 'like', $term)
                    ->orWhere('defect_date', 'like', $term)
                    ->orWhere('defect_priority', 'like', $term)
                    ->orWhere('defect_cat', 'like', $term);
            });
        }

        $columnMap = ['priority' => 'defect_priority', 'category' => 'defect_cat'];
        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'defect_date';
        $sort = $columnMap[$sort] ?? $sort;

        $paginator = $builder->orderBy($sort, $query->direction)->paginate($query->perPage, page: $query->page);

        $rows = collect($paginator->items())->map(fn ($d) => [
            'record_id' => $d->defectID,
            'sl_no' => $d->sl_no,
            'vessel' => $vessels[$d->vesID] ?? '',
            'defect_date' => $d->defect_date,
            'priority' => $d->defect_priority,
            'category' => $d->defect_cat,
            'compl_code' => $d->compl_code,
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
     * Ported from index()/loadData(): vessel/date-range/priority filters,
     * plus the same `vesID IN (SELECT vesID FROM tb_user_vessel WHERE
     * userID=...) AND tb_vessel.vessel_status='ACTIVE'` scoping as the
     * dashlet's legacyTable(), reading tb_defect_list directly from the
     * legacy connection.
     */
    public function legacyFullTable(?string $vesselId, ?string $dateFrom, ?string $dateTo, ?string $priority, TableQuery $query, ?string $legacyUserId): array
    {
        $vessels = LegacyDb::vesselNames();
        $eligibleVesselIds = LegacyDb::assignedVesselIds($legacyUserId)->intersect(LegacyDb::activeVesselIds());

        $builder = DB::connection('legacy')->table('tb_defect_list')->whereIn('vesID', $eligibleVesselIds);

        if ($vesselId !== null) {
            $builder->where('vesID', $vesselId);
        }

        if ($dateFrom !== null && $dateTo !== null) {
            $builder->whereDate('defect_date', '>=', $dateFrom)
                ->whereDate('defect_date', '<=', $dateTo);

            if ($priority !== null) {
                $builder->where('defect_priority', $priority);
            }
        }

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('sl_no', 'like', $term)
                    ->orWhere('defect_date', 'like', $term)
                    ->orWhere('defect_priority', 'like', $term)
                    ->orWhere('defect_cat', 'like', $term)
                    ->orWhere('compl_code', 'like', $term)
                    ->orWhere('defect_description', 'like', $term)
                    ->orWhere('present_status', 'like', $term);
            });
        }

        $columnMap = ['priority' => 'defect_priority', 'category' => 'defect_cat', 'description' => 'defect_description'];
        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'defect_date';
        $sort = $columnMap[$sort] ?? $sort;

        $paginator = $builder->orderBy($sort, $query->direction)
            ->orderBy('sl_no', $query->direction)
            ->paginate($query->perPage, page: $query->page);

        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        $rows = collect($paginator->items())->map(fn ($d) => [
            'id' => $d->defectID,
            'sl_no' => $d->sl_no,
            'vessel' => $vessels[$d->vesID] ?? '',
            'defect_date' => $zeroDateToNull($d->defect_date),
            'priority' => $d->defect_priority,
            'category' => $d->defect_cat,
            'compl_code' => $d->compl_code,
            'description' => $d->defect_description,
            'present_status' => $d->present_status,
            'expected_compl_date' => $zeroDateToNull($d->expected_compl_date),
            'compl_date' => $zeroDateToNull($d->compl_date),
            'can_edit' => true,
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
     * Ported from add_record()'s insert branch, writing to the legacy
     * connection. Legacy's own Add form never actually submits a vesID
     * (see DefectRequest's docblock) so this mirrors this migration's
     * already-established local create() instead: vessel_id is a real
     * required field rather than legacy's silently-broken blank one.
     */
    public function legacyCreate(array $data): array
    {
        $defectId = 'defect'.uniqid();
        $this->legacySave($defectId, $data);

        return $this->legacyRow($defectId);
    }

    /** Ported from add_record()'s edit branch: vesID/vessel_remarks are frozen, matching update(). */
    public function legacyUpdate(string $defectId, array $data): array
    {
        unset($data['vessel_id'], $data['vessel_remarks']);

        $current = DB::connection('legacy')->table('tb_defect_list')->where('defectID', $defectId)->first();
        $data['vessel_id'] = $current->vesID ?? null;

        $this->legacySave($defectId, $data);

        return $this->legacyRow($defectId);
    }

    /**
     * Ported from add_record()'s delete-then-reinsert save branch.
     * expected_compl_date/compl_date are NOT NULL in the legacy schema
     * and use the '0000-00-00' sentinel instead of true NULL.
     */
    private function legacySave(string $defectId, array $data): void
    {
        $legacy = DB::connection('legacy');
        $nullToZeroDate = fn (?string $date) => $date ?? '0000-00-00';

        $legacy->table('tb_defect_list')->where('defectID', $defectId)->delete();

        $legacy->table('tb_defect_list')->insert([
            'defectID' => $defectId,
            'vesID' => $data['vessel_id'],
            'defect_date' => $data['defect_date'],
            'sl_no' => $data['sl_no'],
            'compl_code' => $data['compl_code'],
            'defect_description' => $data['description'],
            'present_status' => $data['present_status'] ?? '',
            'defect_priority' => $data['priority'] ?? '',
            'defect_cat' => $data['category'] ?? '',
            'raised_by' => $data['raised_by'] ?? '',
            'expected_compl_date' => $nullToZeroDate($data['expected_compl_date'] ?? null),
            'compl_date' => $nullToZeroDate($data['compl_date'] ?? null),
            'vessel_remarks' => $data['vessel_remarks'] ?? '',
            'shore_remarks' => $data['shore_remarks'] ?? '',
        ]);
    }

    private function legacyRow(string $defectId): array
    {
        $vessels = LegacyDb::vesselNames();
        $d = DB::connection('legacy')->table('tb_defect_list')->where('defectID', $defectId)->first();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        return $this->toDetailArray([
            'sl_no' => $d->sl_no,
            'vessel' => $vessels[$d->vesID] ?? '',
            'defect_date' => $zeroDateToNull($d->defect_date),
            'priority' => $d->defect_priority,
            'category' => $d->defect_cat,
            'compl_code' => $d->compl_code,
            'description' => $d->defect_description,
            'present_status' => $d->present_status,
            'expected_compl_date' => $zeroDateToNull($d->expected_compl_date),
            'compl_date' => $zeroDateToNull($d->compl_date),
            'raised_by' => $d->raised_by,
            'vessel_remarks' => $d->vessel_remarks,
            'shore_remarks' => $d->shore_remarks,
        ], id: $d->defectID, vesselId: $d->vesID, canEdit: true);
    }

    /** Same as detail(), reading tb_defect_list directly from the legacy connection. */
    public function legacyDetail(string $defectID): ?array
    {
        $d = DB::connection('legacy')->table('tb_defect_list')->where('defectID', $defectID)->first();

        if ($d === null) {
            return null;
        }

        $vessels = LegacyDb::vesselNames();
        $zeroDateToNull = fn (?string $date) => ($date === null || $date === '0000-00-00') ? null : $date;

        return $this->toDetailArray([
            'sl_no' => $d->sl_no,
            'vessel' => $vessels[$d->vesID] ?? '',
            'defect_date' => $zeroDateToNull($d->defect_date),
            'priority' => $d->defect_priority,
            'category' => $d->defect_cat,
            'compl_code' => $d->compl_code,
            'description' => $d->defect_description,
            'present_status' => $d->present_status,
            'expected_compl_date' => $zeroDateToNull($d->expected_compl_date),
            'compl_date' => $zeroDateToNull($d->compl_date),
            'raised_by' => $d->raised_by,
            'vessel_remarks' => $d->vessel_remarks,
            'shore_remarks' => $d->shore_remarks,
        ], id: $d->defectID, vesselId: $d->vesID, canEdit: true);
    }

    /** @param array<string, mixed> $r */
    private function toDetailArray(array $r, int|string $id, int|string|null $vesselId, bool $canEdit): array
    {
        return [
            'id' => $id,
            'sl_no' => $r['sl_no'],
            'vessel' => $r['vessel'],
            'defect_date' => $r['defect_date'],
            'priority' => $r['priority'],
            'category' => $r['category'],
            'compl_code' => $r['compl_code'],
            'description' => $r['description'],
            'present_status' => $r['present_status'],
            'expected_compl_date' => $r['expected_compl_date'],
            'compl_date' => $r['compl_date'],
            'vessel_id' => $vesselId,
            'raised_by' => $r['raised_by'],
            'vessel_remarks' => $r['vessel_remarks'],
            'shore_remarks' => $r['shore_remarks'],
            'can_edit' => $canEdit,
        ];
    }
}
