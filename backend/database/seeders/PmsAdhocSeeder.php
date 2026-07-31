<?php

namespace Database\Seeders;

use App\Models\Pms\PmsAdhoc;
use App\Models\Pms\PmsAdhocInventory;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsEquipment;
use App\Models\Pms\PmsJobClass;
use App\Models\Pms\PmsJobType;
use App\Models\Pms\PmsPart;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class PmsAdhocSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);

        $deck = PmsDepartment::firstOrCreate(['name' => 'Deck']);
        $engine = PmsDepartment::firstOrCreate(['name' => 'Engine']);

        $mechanical = PmsJobClass::firstOrCreate(['name' => 'Mechanical']);
        $electrical = PmsJobClass::firstOrCreate(['name' => 'Electrical']);

        $repair = PmsJobType::firstOrCreate(['name' => 'Repair']);
        $inspection = PmsJobType::firstOrCreate(['name' => 'Inspection']);

        PmsEquipment::where('equipment_code', 'ME-01')->update(['pms_department_id' => $engine->id]);
        PmsEquipment::where('equipment_code', 'AE-01')->update(['pms_department_id' => $engine->id]);

        $mainEngine = PmsEquipment::where('equipment_code', 'ME-01')->first();
        $fuelInjector = PmsPart::where('part_code', 'ME-01.1')->first();

        if ($fuelInjector) {
            $fuelInjector->update(['new_qty' => 10, 'reconditioned_qty' => 4, 'required_qty' => 2, 'unit' => 'PCS']);
        }

        // EQUIPMENT-type ticket: unplanned repair on the Main Engine, consuming one new Fuel Injector.
        $equipmentTicket = PmsAdhoc::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'ticket_no' => 'ADHOC-MVPacificStar-'.now()->year.'-1'],
            [
                'type' => 'EQUIPMENT',
                'pms_department_id' => $engine->id,
                'pms_equipment_id' => $mainEngine?->id,
                'pms_part_id' => $fuelInjector?->id,
                'activity_name' => 'Fuel injector nozzle replacement',
                'pms_job_class_id' => $mechanical->id,
                'pms_job_type_id' => $repair->id,
                'incharge' => 'Chief Engineer',
                'assignee' => '2nd Engineer',
                'work_procedure' => 'Isolate fuel supply, remove and replace faulty nozzle, pressure test.',
                'date_of_activity' => now()->subDays(5),
                'description' => 'Nozzle found leaking during routine watch, replaced to prevent further fuel wastage.',
                'remarks' => 'Old nozzle retained for inspection ashore.',
            ],
        );

        if ($fuelInjector && $equipmentTicket->inventory()->count() === 0) {
            PmsAdhocInventory::create([
                'pms_adhoc_id' => $equipmentTicket->id,
                'pms_part_id' => $fuelInjector->id,
                'new_qty' => 1,
                'reconditioned_qty' => 0,
            ]);
            $fuelInjector->decrement('new_qty', 1);
        }

        // LOCATION-type ticket: non-component ad hoc work with no linked equipment/part.
        PmsAdhoc::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'ticket_no' => 'ADHOC-MVPacificStar-'.now()->year.'-2'],
            [
                'type' => 'LOCATION',
                'pms_department_id' => $deck->id,
                'location' => 'Bridge Wing',
                'sub_location' => 'Port Side',
                'activity_name' => 'Repair damaged railing',
                'pms_job_class_id' => $electrical->id,
                'pms_job_type_id' => $inspection->id,
                'incharge' => 'Bosun',
                'work_procedure' => 'Weld and repaint damaged section of railing.',
                'date_of_activity' => now()->subDays(2),
                'description' => 'Railing damaged during heavy weather, repaired to restore safe access.',
                'remarks' => null,
            ],
        );
    }
}
