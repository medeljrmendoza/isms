<?php

namespace App\Repositories\Pms;

use App\Models\Pms\PmsAdhoc;
use App\Models\Pms\PmsAdhocInventory;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsEquipment;
use App\Models\Pms\PmsJobClass;
use App\Models\Pms\PmsJobType;
use App\Models\Pms\PmsPart;
use App\Models\Vessel;
use App\Support\LegacyDb;
use App\Support\TableQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Ported from Controllers/Pms_work_plan.php. Not ported: file
 * attachments (no file storage anywhere in this migration) and the
 * redundant tb_pms_ticket mirror write — tb_pms_adhoc is already the
 * primary record and the mirror is never read back by anything this
 * module exposes (the View modal's leftover LEFT JOIN to tb_pms_ticket
 * pulls no fields into its response), so PmsAdhoc's own ticket_no
 * serves the same purpose. spectec_main_group/group/sub_group are
 * commented out of both the list view's edit handler and the Add form
 * entirely — dead, matching Pms_activities' dropped classification
 * fields. In-charge/Assignee are ported as plain strings rather than a
 * full pl_position lookup, consistent with Pms_activities' incharge
 * field. The list view's COMPONENT/PART columns render raw
 * equipmentID/partsID values in legacy (loadData() selects the FK
 * columns directly, never joining to their name) — ported here as the
 * actual joined names instead, since that's clearly the columns' intent.
 */
class PmsAdhocRepository
{
    private const COLUMNS = [
        ['key' => 'ticket_no', 'label' => 'TICKET NO.', 'sortable' => true],
        ['key' => 'department', 'label' => 'DEPARTMENT', 'sortable' => true],
        ['key' => 'component', 'label' => 'COMPONENT', 'sortable' => false],
        ['key' => 'part', 'label' => 'PART', 'sortable' => false],
        ['key' => 'activity_name', 'label' => 'ACTIVITY', 'sortable' => true],
        ['key' => 'incharge', 'label' => 'IN-CHARGE', 'sortable' => true],
        ['key' => 'date_of_activity', 'label' => 'DATE OF ACTIVITY', 'sortable' => true],
    ];

    public static function columns(): array
    {
        return self::COLUMNS;
    }

    /** @return array<int, array{id:int,label:string}> */
    public function vesselOptions(): array
    {
        return Vessel::query()->orderBy('name')->get()
            ->map(fn (Vessel $v) => ['id' => $v->id, 'label' => $v->display_name])
            ->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function departmentOptions(): array
    {
        return PmsDepartment::query()->orderBy('name')->get()
            ->map(fn (PmsDepartment $d) => ['id' => $d->id, 'label' => $d->name])->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function jobClassOptions(): array
    {
        return PmsJobClass::query()->orderBy('name')->get()
            ->map(fn (PmsJobClass $c) => ['id' => $c->id, 'label' => $c->name])->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function jobTypeOptions(): array
    {
        return PmsJobType::query()->orderBy('name')->get()
            ->map(fn (PmsJobType $t) => ['id' => $t->id, 'label' => $t->name])->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function componentOptions(int $vesselId): array
    {
        return PmsEquipment::where('vessel_id', $vesselId)->where('is_active', true)->orderBy('equipment_code')->get()
            ->map(fn (PmsEquipment $e) => ['id' => $e->id, 'label' => "({$e->equipment_code}) {$e->equipment_name}"])->all();
    }

    /** @return array<int, array{id:int,label:string}> */
    public function partOptions(int $equipmentId): array
    {
        return PmsPart::where('pms_equipment_id', $equipmentId)->where('is_active', true)->orderBy('part_code')->get()
            ->map(fn (PmsPart $p) => ['id' => $p->id, 'label' => $p->part_name])->all();
    }

    /** Ported from get_partname(): typeahead search used by the inventory Add-Item modal. */
    public function searchParts(string $key): array
    {
        return PmsPart::query()->with('equipment')
            ->where(function (Builder $q) use ($key) {
                $q->where('part_name', 'like', "%{$key}%")
                    ->orWhereHas('equipment', fn (Builder $eq) => $eq->where('equipment_name', 'like', "%{$key}%"));
            })
            ->orderBy('part_name')
            ->get()
            ->map(fn (PmsPart $p) => [
                'id' => $p->id,
                'part_name' => $p->part_name,
                'equipment_name' => $p->equipment?->equipment_name,
                'required_qty' => $p->required_qty,
                'unit' => $p->unit,
                'new_qty' => $p->new_qty,
                'reconditioned_qty' => $p->reconditioned_qty,
            ])->all();
    }

    public function table(int $vesselId, TableQuery $query): LengthAwarePaginator
    {
        $builder = PmsAdhoc::where('vessel_id', $vesselId)->with(['department', 'equipment', 'part']);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function (Builder $q) use ($term) {
                $q->where('activity_name', 'like', $term)
                    ->orWhere('incharge', 'like', $term)
                    ->orWhereHas('department', fn (Builder $d) => $d->where('name', 'like', $term))
                    ->orWhereHas('equipment', fn (Builder $e) => $e->where('equipment_name', 'like', $term))
                    ->orWhereHas('part', fn (Builder $p) => $p->where('part_name', 'like', $term));
            });
        }

        $sortable = ['ticket_no', 'activity_name', 'incharge', 'date_of_activity'];
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_activity';

        return $builder->orderBy($sort, $query->direction)
            ->paginate($query->perPage, page: $query->page);
    }

    public function detail(PmsAdhoc $adhoc): array
    {
        $adhoc->load(['vessel', 'department', 'equipment', 'part', 'jobClass', 'jobType', 'inventory.part.equipment']);

        return [
            'id' => $adhoc->id,
            'ticket_no' => $adhoc->ticket_no,
            'vessel_id' => $adhoc->vessel_id,
            'vessel' => $adhoc->vessel->display_name,
            'type' => $adhoc->type,
            'pms_department_id' => $adhoc->pms_department_id,
            'department' => $adhoc->department?->name,
            'pms_equipment_id' => $adhoc->pms_equipment_id,
            'equipment_name' => $adhoc->equipment?->equipment_name,
            'pms_part_id' => $adhoc->pms_part_id,
            'part_name' => $adhoc->part?->part_name,
            'location' => $adhoc->location,
            'sub_location' => $adhoc->sub_location,
            'activity_name' => $adhoc->activity_name,
            'pms_job_class_id' => $adhoc->pms_job_class_id,
            'job_class' => $adhoc->jobClass?->name,
            'pms_job_type_id' => $adhoc->pms_job_type_id,
            'job_type' => $adhoc->jobType?->name,
            'incharge' => $adhoc->incharge,
            'assignee' => $adhoc->assignee,
            'work_procedure' => $adhoc->work_procedure,
            'date_of_activity' => $adhoc->date_of_activity->format('Y-m-d'),
            'description' => $adhoc->description,
            'remarks' => $adhoc->remarks,
            'inventory' => $adhoc->inventory->map(fn (PmsAdhocInventory $i) => [
                'pms_part_id' => $i->pms_part_id,
                'part_name' => $i->part?->part_name,
                'equipment_name' => $i->part?->equipment?->equipment_name,
                'new_qty' => $i->new_qty,
                'reconditioned_qty' => $i->reconditioned_qty,
            ])->all(),
        ];
    }

    /** @param array<int, array{pms_part_id:int, new_qty?:int, reconditioned_qty?:int}> $inventoryItems */
    public function create(array $data, array $inventoryItems): PmsAdhoc
    {
        return DB::transaction(function () use ($data, $inventoryItems) {
            $this->assertSufficientStock($inventoryItems, collect());

            $vessel = Vessel::findOrFail($data['vessel_id']);
            $data['ticket_no'] = $this->nextTicketNo($vessel);
            $data = $this->resolveDepartment($data);

            $adhoc = PmsAdhoc::create($data);
            $this->applyInventory($adhoc, $inventoryItems);

            return $adhoc;
        });
    }

    /**
     * Ported from add_item()'s edit branch: vesID is frozen. Legacy
     * tracks old-vs-new quantities per part with parallel old_new_qty[]/
     * old_reconditioned_qty[] arrays to compute an increment/decrement
     * delta; simplified here to restore every existing row's stock
     * first, then re-apply the requested quantities as if fresh —
     * mathematically equivalent, without the parallel array bookkeeping.
     */
    public function update(PmsAdhoc $adhoc, array $data, array $inventoryItems): PmsAdhoc
    {
        return DB::transaction(function () use ($adhoc, $data, $inventoryItems) {
            $existingByPart = $adhoc->inventory()->get()->keyBy('pms_part_id');
            $this->assertSufficientStock($inventoryItems, $existingByPart);

            foreach ($existingByPart as $row) {
                $this->adjustStock($row->pms_part_id, $row->new_qty, $row->reconditioned_qty);
            }
            $adhoc->inventory()->delete();

            unset($data['vessel_id']);
            $data = $this->resolveDepartment($data);
            $adhoc->update($data);
            $this->applyInventory($adhoc, $inventoryItems);

            return $adhoc->fresh();
        });
    }

    /**
     * Ported from add_item(): for EQUIPMENT-type tickets, the department
     * is always derived from the selected component's own department
     * rather than trusted from the form (which has no department field
     * to submit for this branch in the first place).
     */
    private function resolveDepartment(array $data): array
    {
        if (($data['type'] ?? null) === 'EQUIPMENT' && ! empty($data['pms_equipment_id'])) {
            $data['pms_department_id'] = PmsEquipment::find($data['pms_equipment_id'])?->pms_department_id;
        }

        return $data;
    }

    /** Ported from delete_item(): restores each used part's stock before removing the record. */
    public function delete(PmsAdhoc $adhoc): void
    {
        DB::transaction(function () use ($adhoc) {
            foreach ($adhoc->inventory as $row) {
                $this->adjustStock($row->pms_part_id, $row->new_qty, $row->reconditioned_qty);
            }
            $adhoc->delete();
        });
    }

    /** @param array<int, array{pms_part_id:int, new_qty?:int, reconditioned_qty?:int}> $items */
    private function assertSufficientStock(array $items, Collection $existingByPart): void
    {
        foreach ($items as $item) {
            $part = PmsPart::findOrFail($item['pms_part_id']);
            $existing = $existingByPart->get($item['pms_part_id']);
            $availableNew = $part->new_qty + ($existing->new_qty ?? 0);
            $availableRecon = $part->reconditioned_qty + ($existing->reconditioned_qty ?? 0);

            if (($item['new_qty'] ?? 0) > $availableNew || ($item['reconditioned_qty'] ?? 0) > $availableRecon) {
                throw ValidationException::withMessages([
                    'inventory' => ["Item's qty on inventory is not enough! Kindly check inventory or do a physical count."],
                ]);
            }
        }
    }

    /** @param array<int, array{pms_part_id:int, new_qty?:int, reconditioned_qty?:int}> $items */
    private function applyInventory(PmsAdhoc $adhoc, array $items): void
    {
        foreach ($items as $item) {
            $newQty = $item['new_qty'] ?? 0;
            $reconQty = $item['reconditioned_qty'] ?? 0;

            PmsAdhocInventory::create([
                'pms_adhoc_id' => $adhoc->id,
                'pms_part_id' => $item['pms_part_id'],
                'new_qty' => $newQty,
                'reconditioned_qty' => $reconQty,
            ]);

            $this->adjustStock($item['pms_part_id'], -$newQty, -$reconQty);
        }
    }

    private function adjustStock(int $partId, int $newQtyDelta, int $reconQtyDelta): void
    {
        PmsPart::where('id', $partId)->update([
            'new_qty' => DB::raw("new_qty + ({$newQtyDelta})"),
            'reconditioned_qty' => DB::raw("reconditioned_qty + ({$reconQtyDelta})"),
        ]);
    }

    /** Ported from add_item()'s ticket ID generator, simplified to a single vessel+year sequence count. */
    private function nextTicketNo(Vessel $vessel): string
    {
        $year = Carbon::today()->year;
        $count = PmsAdhoc::where('vessel_id', $vessel->id)->whereYear('created_at', $year)->count();
        $shortName = str_replace(' ', '', $vessel->prefix.$vessel->name);

        return "ADHOC-{$shortName}-{$year}-".($count + 1);
    }

    /*
     * ------------------------------------------------------------------
     * Legacy DB support. Not ported (same as the local port above, see
     * class docblock): file attachments and the redundant tb_pms_ticket
     * mirror write — legacy's own view_item() never reads a single field
     * back from that mirror, tb_pms_adhoc already being the primary
     * record.
     * ------------------------------------------------------------------
     */

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

    /** @return array<int, array{id:string,label:string}> */
    public function legacyDepartmentOptions(): array
    {
        return DB::connection('legacy')->table('tb_pms_department')
            ->orderBy('department_name')
            ->get()
            ->map(fn ($d) => ['id' => $d->deptID, 'label' => $d->department_name])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyJobClassOptions(): array
    {
        return DB::connection('legacy')->table('pl_pms_job_class')
            ->orderBy('job_class')
            ->get()
            ->map(fn ($c) => ['id' => $c->jobID, 'label' => $c->job_class])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyJobTypeOptions(): array
    {
        return DB::connection('legacy')->table('pl_pms_job_type')
            ->orderBy('job_type')
            ->get()
            ->map(fn ($t) => ['id' => $t->jobtypeID, 'label' => $t->job_type])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyComponentOptions(string $vesselId): array
    {
        return DB::connection('legacy')->table('tb_pms_equipment')
            ->where('vesID', $vesselId)->where('status', 1)
            ->orderBy('equipment_code')
            ->get()
            ->map(fn ($e) => ['id' => $e->equipmentID, 'label' => "({$e->equipment_code}) {$e->equipment_name}"])
            ->all();
    }

    /** @return array<int, array{id:string,label:string}> */
    public function legacyPartOptions(string $equipmentId): array
    {
        return DB::connection('legacy')->table('tb_pms_parts')
            ->where('equipmentID', $equipmentId)->where('status', 1)
            ->orderBy('part_code')
            ->get()
            ->map(fn ($p) => ['id' => $p->partsID, 'label' => $p->part_name])
            ->all();
    }

    /** Ported from get_partname(). */
    public function legacySearchParts(string $key): array
    {
        return DB::connection('legacy')->table('tb_pms_parts as tp')
            ->leftJoin('tb_pms_equipment as te', 'te.equipmentID', '=', 'tp.equipmentID')
            ->where(fn ($q) => $q->where('tp.part_name', 'like', "%{$key}%")->orWhere('te.equipment_name', 'like', "%{$key}%"))
            ->orderBy('te.equipment_name')->orderBy('tp.part_name')
            ->select(['tp.partsID', 'tp.part_name', 'te.equipment_name', 'tp.required_qty', 'tp.unit', 'tp.new_qty', 'tp.reconditioned_qty'])
            ->get()
            ->map(fn ($p) => [
                'id' => $p->partsID,
                'part_name' => $p->part_name,
                'equipment_name' => $p->equipment_name,
                'required_qty' => (int) $p->required_qty,
                'unit' => $p->unit,
                'new_qty' => (int) $p->new_qty,
                'reconditioned_qty' => (int) $p->reconditioned_qty,
            ])->all();
    }

    /** Ported from loadData(), reading tb_pms_adhoc directly from the legacy connection. */
    public function legacyTable(string $vesselId, TableQuery $query): LengthAwarePaginator
    {
        $builder = DB::connection('legacy')->table('tb_pms_adhoc as ta')
            ->leftJoin('tb_pms_department', 'tb_pms_department.deptID', '=', 'ta.deptID')
            ->leftJoin('pl_position', 'pl_position.posID', '=', 'ta.work_plan_incharge')
            ->leftJoin('tb_pms_equipment', 'tb_pms_equipment.equipmentID', '=', 'ta.equipmentID')
            ->leftJoin('tb_pms_parts', 'tb_pms_parts.partsID', '=', 'ta.partsID')
            ->where('ta.vesID', $vesselId);

        if ($query->search !== null) {
            $term = "%{$query->search}%";
            $builder->where(function ($q) use ($term) {
                $q->where('ta.work_plan_activity', 'like', $term)
                    ->orWhere('pl_position.long_posname', 'like', $term)
                    ->orWhere('tb_pms_department.department_name', 'like', $term)
                    ->orWhere('tb_pms_equipment.equipment_name', 'like', $term)
                    ->orWhere('tb_pms_parts.part_name', 'like', $term);
            });
        }

        $columnMap = ['ticket_no' => 'ta.adhocID', 'activity_name' => 'ta.work_plan_activity', 'incharge' => 'pl_position.long_posname', 'date_of_activity' => 'ta.dateof_activity'];
        $sortable = array_column(array_filter(self::COLUMNS, fn ($c) => $c['sortable']), 'key');
        $sort = in_array($query->sort, $sortable, true) ? $query->sort : 'date_of_activity';
        $sort = $columnMap[$sort] ?? 'ta.dateof_activity';

        return $builder->orderBy($sort, $query->direction)
            ->select([
                'ta.adhocID', 'ta.work_plan_activity', 'ta.dateof_activity',
                'tb_pms_department.department_name', 'pl_position.long_posname as incharge',
                'tb_pms_equipment.equipment_name', 'tb_pms_parts.part_name',
            ])
            ->paginate($query->perPage, page: $query->page);
    }

    /** Ported from view_item(). */
    public function legacyDetail(string $adhocId): array
    {
        $a = DB::connection('legacy')->table('tb_pms_adhoc as ta')
            ->leftJoin('tb_vessel', 'tb_vessel.vesID', '=', 'ta.vesID')
            ->leftJoin('tb_pms_department', 'tb_pms_department.deptID', '=', 'ta.deptID')
            ->leftJoin('tb_pms_equipment', 'tb_pms_equipment.equipmentID', '=', 'ta.equipmentID')
            ->leftJoin('tb_pms_parts', 'tb_pms_parts.partsID', '=', 'ta.partsID')
            ->leftJoin('pl_pms_job_class', 'pl_pms_job_class.jobID', '=', 'ta.work_plan_jobID')
            ->leftJoin('pl_pms_job_type', 'pl_pms_job_type.jobtypeID', '=', 'ta.work_plan_jobtypeID')
            ->leftJoin('pl_position', 'pl_position.posID', '=', 'ta.work_plan_incharge')
            ->where('ta.adhocID', $adhocId)
            ->select([
                'ta.adhocID', 'ta.vesID', 'ta.deptID', 'ta.equipmentID', 'ta.partsID',
                'ta.work_plan_type', 'ta.work_plan_location', 'ta.work_plan_sub_location', 'ta.work_plan_activity',
                'ta.work_plan_jobID', 'ta.work_plan_jobtypeID', 'ta.work_plan_incharge', 'ta.work_plan_assignee',
                'ta.work_plan_work_procedure', 'ta.dateof_activity', 'ta.description', 'ta.remarks',
                'tb_vessel.vessel_name', 'tb_vessel.vessel_prefix', 'tb_pms_department.department_name',
                'tb_pms_equipment.equipment_name', 'tb_pms_parts.part_name',
                'pl_pms_job_class.job_class', 'pl_pms_job_type.job_type', 'pl_position.long_posname as incharge_name',
            ])
            ->first();

        abort_if($a === null, 404);

        $assignee = $a->work_plan_assignee !== '' ? DB::connection('legacy')->table('pl_position')->where('posID', $a->work_plan_assignee)->first() : null;

        $inventory = DB::connection('legacy')->table('tb_pms_adhoc_inventory as i')
            ->leftJoin('tb_pms_parts as p', 'p.partsID', '=', 'i.partsID')
            ->leftJoin('tb_pms_equipment as e', 'e.equipmentID', '=', 'p.equipmentID')
            ->where('i.ticketID', $adhocId)
            ->select(['i.partsID', 'p.part_name', 'e.equipment_name', 'i.new_qty', 'i.reconditioned_qty'])
            ->get();

        // work_plan_type is blank on every row in the real data (legacy
        // itself never actually writes "EQUIPMENT"/"LOCATION" there), so
        // it can't be used to tell the two cases apart. delete_item()'s
        // own display-value lookups instead key off whether equipmentID
        // is non-empty ("if($adhoc_details->equipmentID != ''){ ... }"),
        // which this mirrors.
        $isEquipment = $a->equipmentID !== '';

        return [
            'id' => $a->adhocID,
            'ticket_no' => $a->adhocID,
            'vessel_id' => $a->vesID,
            'vessel' => trim("{$a->vessel_prefix} {$a->vessel_name}"),
            'type' => $isEquipment ? 'EQUIPMENT' : 'LOCATION',
            'pms_department_id' => $a->deptID,
            'department' => $a->department_name,
            'pms_equipment_id' => $isEquipment && $a->equipmentID !== '' ? $a->equipmentID : null,
            'equipment_name' => $isEquipment ? $a->equipment_name : null,
            'pms_part_id' => $isEquipment && $a->partsID !== '' ? $a->partsID : null,
            'part_name' => $isEquipment ? $a->part_name : null,
            'location' => $a->work_plan_location,
            'sub_location' => $a->work_plan_sub_location,
            'activity_name' => $a->work_plan_activity,
            'pms_job_class_id' => $a->work_plan_jobID !== '' ? $a->work_plan_jobID : null,
            'job_class' => $a->job_class,
            'pms_job_type_id' => $a->work_plan_jobtypeID !== '' ? $a->work_plan_jobtypeID : null,
            'job_type' => $a->job_type,
            'incharge' => $a->incharge_name,
            'assignee' => $assignee->long_posname ?? null,
            'work_procedure' => $a->work_plan_work_procedure,
            'date_of_activity' => $a->dateof_activity,
            'description' => $a->description,
            'remarks' => $a->remarks,
            'inventory' => $inventory->map(fn ($i) => [
                'pms_part_id' => $i->partsID,
                'part_name' => $i->part_name,
                'equipment_name' => $i->equipment_name,
                'new_qty' => (int) $i->new_qty,
                'reconditioned_qty' => (int) $i->reconditioned_qty,
            ])->all(),
        ];
    }

    /**
     * Ported from add_item()'s insert branch. incharge/assignee arrive
     * as posID strings here (unlike the local port's plain-string
     * incharge/assignee) since legacy's work_plan_incharge/assignee
     * columns are themselves posID foreign keys.
     *
     * @param  array<int, array{pms_part_id:string, new_qty?:int, reconditioned_qty?:int}>  $inventoryItems
     */
    public function legacyCreate(string $vesselId, array $data, array $inventoryItems): array
    {
        $vessel = DB::connection('legacy')->table('tb_vessel')->where('vesID', $vesselId)->first();
        abort_if($vessel === null, 404);

        $this->legacyAssertSufficientStock($inventoryItems, collect());

        $adhocId = 'ADHOC'.uniqid();
        $this->legacySaveAdhoc($adhocId, $vesselId, $data);
        $this->legacyApplyInventory($vesselId, $adhocId, $inventoryItems, collect());

        return $this->legacyDetail($adhocId);
    }

    /**
     * Ported from add_item()'s edit branch: vesID is frozen, mirroring
     * update()'s restore-then-reapply inventory strategy.
     *
     * @param  array<int, array{pms_part_id:string, new_qty?:int, reconditioned_qty?:int}>  $inventoryItems
     */
    public function legacyUpdate(string $adhocId, array $data, array $inventoryItems): array
    {
        $adhoc = DB::connection('legacy')->table('tb_pms_adhoc')->where('adhocID', $adhocId)->first();
        abort_if($adhoc === null, 404);

        $existing = DB::connection('legacy')->table('tb_pms_adhoc_inventory')->where('ticketID', $adhocId)->get()->keyBy('partsID');
        $this->legacyAssertSufficientStock($inventoryItems, $existing);

        $this->legacySaveAdhoc($adhocId, $adhoc->vesID, $data);
        $this->legacyApplyInventory($adhoc->vesID, $adhocId, $inventoryItems, $existing);

        return $this->legacyDetail($adhocId);
    }

    private function legacySaveAdhoc(string $adhocId, string $vesselId, array $data): void
    {
        $legacy = DB::connection('legacy');
        $deptId = $data['pms_department_id'] ?? null;

        if (($data['type'] ?? null) === 'EQUIPMENT' && ! empty($data['pms_equipment_id'])) {
            $equipment = $legacy->table('tb_pms_equipment')->where('equipmentID', $data['pms_equipment_id'])->first();

            // Only override when the equipment reference actually resolves
            // — a handful of legacy rows have stray free text in equipmentID
            // instead of a real FK (see legacyDetail()), and falling back to
            // null here would silently wipe out an already-known deptID.
            if ($equipment !== null) {
                $deptId = $equipment->deptID;
            }
        }

        $legacy->table('tb_pms_adhoc')->where('adhocID', $adhocId)->delete();
        $legacy->table('tb_pms_adhoc')->insert([
            'adhocID' => $adhocId,
            'vesID' => $vesselId,
            'deptID' => $deptId ?? '',
            'equipmentID' => $data['pms_equipment_id'] ?? '',
            'partsID' => $data['pms_part_id'] ?? '',
            'work_plan_type' => $data['type'],
            'work_plan_location' => $data['location'] ?? '',
            'work_plan_sub_location' => $data['sub_location'] ?? '',
            'work_plan_activity' => $data['activity_name'],
            'work_plan_jobID' => $data['pms_job_class_id'] ?? '',
            'work_plan_jobtypeID' => $data['pms_job_type_id'] ?? '',
            'work_plan_incharge' => $data['incharge'],
            'work_plan_assignee' => $data['assignee'] ?? '',
            'work_plan_work_procedure' => $data['work_procedure'] ?? '',
            'dateof_activity' => $data['date_of_activity'],
            'description' => $data['description'],
            'remarks' => $data['remarks'] ?? '',
            'datetime' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Ported from add_item()'s $inventory_status check.
     *
     * @param  array<int, array{pms_part_id:string, new_qty?:int, reconditioned_qty?:int}>  $items
     * @param  Collection<string, object>  $existingByPart
     */
    private function legacyAssertSufficientStock(array $items, Collection $existingByPart): void
    {
        foreach ($items as $item) {
            $part = DB::connection('legacy')->table('tb_pms_parts')->where('partsID', $item['pms_part_id'])->first();
            abort_if($part === null, 404);

            $existing = $existingByPart->get($item['pms_part_id']);
            $availableNew = (int) $part->new_qty + (int) ($existing->new_qty ?? 0);
            $availableRecon = (int) $part->reconditioned_qty + (int) ($existing->reconditioned_qty ?? 0);

            if (($item['new_qty'] ?? 0) > $availableNew || ($item['reconditioned_qty'] ?? 0) > $availableRecon) {
                throw ValidationException::withMessages([
                    'inventory' => ["Item's qty on inventory is not enough! Kindly check inventory or do a physical count."],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array{pms_part_id:string, new_qty?:int, reconditioned_qty?:int}>  $items
     * @param  Collection<string, object>  $existingByPart
     */
    private function legacyApplyInventory(string $vesselId, string $adhocId, array $items, Collection $existingByPart): void
    {
        $legacy = DB::connection('legacy');

        foreach ($existingByPart as $row) {
            $this->legacyAdjustStock($row->partsID, (int) $row->new_qty, (int) $row->reconditioned_qty);
        }
        $legacy->table('tb_pms_adhoc_inventory')->where('ticketID', $adhocId)->delete();

        foreach ($items as $item) {
            $newQty = $item['new_qty'] ?? 0;
            $reconQty = $item['reconditioned_qty'] ?? 0;

            $legacy->table('tb_pms_adhoc_inventory')->insert([
                'adhocInventoryID' => uniqid('adInv'),
                'vesID' => $vesselId,
                'ticketID' => $adhocId,
                'partsID' => $item['pms_part_id'],
                'new_qty' => $newQty,
                'used_qty' => 0,
                'reconditioned_qty' => $reconQty,
                'refurbished_qty' => 0,
                'datetime' => now()->toDateTimeString(),
            ]);

            $this->legacyAdjustStock($item['pms_part_id'], -$newQty, -$reconQty);
        }
    }

    private function legacyAdjustStock(string $partId, int $newQtyDelta, int $reconQtyDelta): void
    {
        DB::connection('legacy')->table('tb_pms_parts')->where('partsID', $partId)->update([
            'new_qty' => DB::raw("new_qty + ({$newQtyDelta})"),
            'reconditioned_qty' => DB::raw("reconditioned_qty + ({$reconQtyDelta})"),
        ]);
    }

    /** Ported from delete_item(): restores each used part's stock before removing the record. */
    public function legacyDelete(string $adhocId): void
    {
        $legacy = DB::connection('legacy');

        $inventory = $legacy->table('tb_pms_adhoc_inventory')->where('ticketID', $adhocId)->get();
        foreach ($inventory as $row) {
            $this->legacyAdjustStock($row->partsID, (int) $row->new_qty, (int) $row->reconditioned_qty);
        }

        $legacy->table('tb_pms_adhoc_inventory')->where('ticketID', $adhocId)->delete();
        $legacy->table('tb_pms_adhoc')->where('adhocID', $adhocId)->delete();
    }
}
