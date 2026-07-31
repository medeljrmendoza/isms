<?php

namespace Database\Seeders;

use App\Models\Pms\PmsActivity;
use App\Models\Pms\PmsEquipment;
use App\Models\Pms\PmsPart;
use App\Models\Pms\PmsRunningHoursEquipment;
use App\Models\Pms\PmsRunningHoursEquipmentDetail;
use App\Models\Pms\PmsRunningHoursEquipmentDetailHistory;
use App\Models\Pms\PmsRunningHoursPart;
use App\Models\Pms\PmsRunningHoursPartDetail;
use App\Models\Vessel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PmsRunningHoursSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);

        $today = Carbon::today();
        $month = $today->month;
        $year = $today->year;
        $previous = $today->copy()->subMonthNoOverflow();

        // Main Engine — component-level tracking, with a linked PMS
        // activity whose no_of_hours cascades from this equipment's entries.
        $mainEngine = PmsEquipment::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'equipment_code' => 'ME-01'],
            ['equipment_name' => 'Main Engine', 'is_active' => true],
        );
        $mePart = PmsPart::updateOrCreate(
            ['pms_equipment_id' => $mainEngine->id, 'part_code' => 'ME-01.1'],
            ['part_name' => 'Fuel Injector', 'is_main' => true, 'is_active' => true],
        );

        $meRh = PmsRunningHoursEquipment::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'pms_equipment_id' => $mainEngine->id],
            ['update_by_component' => true, 'is_active' => true],
        );
        $meDetail = PmsRunningHoursEquipmentDetail::updateOrCreate(
            ['pms_running_hours_equipment_id' => $meRh->id],
            ['since_delivery' => 18500, 'monthly_rh' => 48, 'daily_hours' => ['1' => 24, '2' => 24], 'month' => $month, 'year' => $year],
        );
        PmsRunningHoursEquipmentDetailHistory::updateOrCreate(
            ['pms_running_hours_equipment_id' => $meRh->id, 'month' => $previous->month, 'year' => $previous->year],
            ['since_delivery' => 18452, 'monthly_rh' => 720, 'daily_hours' => array_fill_keys(range(1, 30), 24)],
        );

        $mePartRh = PmsRunningHoursPart::updateOrCreate(
            ['pms_equipment_id' => $mainEngine->id, 'pms_parts_id' => $mePart->id],
        );
        PmsRunningHoursPartDetail::updateOrCreate(
            ['pms_running_hours_parts_id' => $mePartRh->id],
            [
                'since_delivery' => 18500,
                'since_last_overhaul' => 1200,
                'date_last_overhauled' => $today->copy()->subMonths(3),
                'monthly_rh' => 48,
                'daily_hours' => ['1' => 24, '2' => 24],
                'month' => $month,
                'year' => $year,
            ],
        );

        PmsActivity::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'activity_name' => 'Main Engine Top Overhaul'],
            [
                'pms_equipment_id' => $mainEngine->id,
                'unit' => 'H', 'min_count_interval' => 4000, 'max_count_interval' => 6000,
                'no_of_hours' => 1200, 'since_delivery' => 18500,
                'due_date' => null, 'is_postponed' => false, 'is_active' => true,
            ],
        );

        // Auxiliary Engine 1 — component-level tracking, no linked activity.
        $auxEngine = PmsEquipment::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'equipment_code' => 'AE-01'],
            ['equipment_name' => 'Auxiliary Engine No. 1', 'is_active' => true],
        );
        $aeRh = PmsRunningHoursEquipment::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'pms_equipment_id' => $auxEngine->id],
            ['update_by_component' => true, 'is_active' => true],
        );
        PmsRunningHoursEquipmentDetail::updateOrCreate(
            ['pms_running_hours_equipment_id' => $aeRh->id],
            ['since_delivery' => 9800, 'monthly_rh' => 20, 'daily_hours' => ['1' => 20], 'month' => $month, 'year' => $year],
        );

        // Steering Gear — parts-level tracking only (update_by_component
        // false): the grid shows this row blank, matching legacy.
        $steeringGear = PmsEquipment::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'equipment_code' => 'SG-01'],
            ['equipment_name' => 'Steering Gear', 'is_active' => true],
        );
        PmsRunningHoursEquipment::updateOrCreate(
            ['vessel_id' => $pacificStar->id, 'pms_equipment_id' => $steeringGear->id],
            ['update_by_component' => false, 'is_active' => true],
        );
    }
}
