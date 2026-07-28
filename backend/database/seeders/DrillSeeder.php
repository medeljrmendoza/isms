<?php

namespace Database\Seeders;

use App\Models\DrillList;
use App\Models\DrillReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class DrillSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $fireDrill = DrillList::firstOrCreate(
            ['name' => 'Fire Drill'],
            ['frequency_type' => 'M', 'frequency_count' => 1, 'applies_to_all_vessels' => true, 'is_active' => true],
        );
        $abandonShipDrill = DrillList::firstOrCreate(
            ['name' => 'Abandon Ship Drill'],
            ['frequency_type' => 'M', 'frequency_count' => 1, 'applies_to_all_vessels' => true, 'is_active' => true],
        );
        // Restricted to Pacific Star only — exercises the vessel_access pivot branch.
        $lifeboatDrill = DrillList::firstOrCreate(
            ['name' => 'Lifeboat Drill'],
            ['frequency_type' => 'W', 'frequency_count' => 2, 'applies_to_all_vessels' => false, 'is_active' => true],
        );
        $lifeboatDrill->vessels()->syncWithoutDetaching([$pacificStar->id]);
        // Inactive — should be excluded even though it has an overdue-looking report.
        $inactiveDrill = DrillList::firstOrCreate(
            ['name' => 'Retired Drill'],
            ['frequency_type' => 'M', 'frequency_count' => 1, 'applies_to_all_vessels' => true, 'is_active' => false],
        );

        $reports = [
            // Pacific Star: overdue (last done 45 days ago, monthly).
            ['drill_list_id' => $fireDrill->id, 'vessel_id' => $pacificStar->id, 'drill_date' => now()->subDays(45)],
            // Pacific Star: upcoming (last done 5 days ago, due within 30 days).
            ['drill_list_id' => $abandonShipDrill->id, 'vessel_id' => $pacificStar->id, 'drill_date' => now()->subDays(5)],
            // Pacific Star: overdue (biweekly, last done 100 days ago).
            ['drill_list_id' => $lifeboatDrill->id, 'vessel_id' => $pacificStar->id, 'drill_date' => now()->subDays(100)],
            // Pacific Star: excluded — drill list itself is inactive.
            ['drill_list_id' => $inactiveDrill->id, 'vessel_id' => $pacificStar->id, 'drill_date' => now()->subDays(90)],
            // Coral Voyager: upcoming (last done 10 days ago, monthly).
            ['drill_list_id' => $fireDrill->id, 'vessel_id' => $coralVoyager->id, 'drill_date' => now()->subDays(10)],
            // Coral Voyager: no Abandon Ship report at all — excluded entirely (matches legacy's "no baseline yet" skip).
        ];

        foreach ($reports as $report) {
            DrillReport::updateOrCreate(
                ['drill_list_id' => $report['drill_list_id'], 'vessel_id' => $report['vessel_id']],
                $report,
            );
        }
    }
}
