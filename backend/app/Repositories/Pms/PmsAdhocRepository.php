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
}
