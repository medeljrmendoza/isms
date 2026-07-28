<?php

namespace Database\Seeders;

use App\Models\PmsActivity;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class PmsSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $activities = [
            // Pacific Star — date-based, overdue.
            [
                'vessel_id' => $pacificStar->id,
                'activity_name' => 'Overdue date-based activity',
                'unit' => 'D', 'min_count_interval' => 0, 'max_count_interval' => 0,
                'no_of_hours' => 0, 'since_delivery' => null,
                'due_date' => now()->subDays(10), 'is_postponed' => false, 'is_active' => true,
            ],
            // Pacific Star — date-based, due within 30 days.
            [
                'vessel_id' => $pacificStar->id,
                'activity_name' => 'Upcoming date-based activity',
                'unit' => 'D', 'min_count_interval' => 0, 'max_count_interval' => 0,
                'no_of_hours' => 0, 'since_delivery' => null,
                'due_date' => now()->addDays(20), 'is_postponed' => false, 'is_active' => true,
            ],
            // Pacific Star — running-hours-based, past its max interval.
            [
                'vessel_id' => $pacificStar->id,
                'activity_name' => 'Overdue running-hours activity',
                'unit' => 'H', 'min_count_interval' => 0, 'max_count_interval' => 5000,
                'no_of_hours' => 5100, 'since_delivery' => 5100,
                'due_date' => null, 'is_postponed' => false, 'is_active' => true,
            ],
            // Pacific Star — running-hours-based, within 720h of its min interval.
            [
                'vessel_id' => $pacificStar->id,
                'activity_name' => 'Upcoming running-hours activity',
                'unit' => 'H', 'min_count_interval' => 3000, 'max_count_interval' => 5000,
                'no_of_hours' => 2800, 'since_delivery' => 2800,
                'due_date' => null, 'is_postponed' => false, 'is_active' => true,
            ],
            // Pacific Star — postponed, short-circuits before the overdue due_date is even considered.
            [
                'vessel_id' => $pacificStar->id,
                'activity_name' => 'Postponed activity',
                'unit' => 'D', 'min_count_interval' => 0, 'max_count_interval' => 0,
                'no_of_hours' => 0, 'since_delivery' => null,
                'due_date' => now()->subDays(500), 'is_postponed' => true, 'is_active' => true,
            ],
            // Pacific Star — since_delivery is exactly 0: excluded from upcoming
            // even though the range would otherwise qualify (legacy's since_delivery!="0" guard).
            [
                'vessel_id' => $pacificStar->id,
                'activity_name' => 'Zero running-hours activity',
                'unit' => 'H', 'min_count_interval' => 100, 'max_count_interval' => 200,
                'no_of_hours' => 50, 'since_delivery' => 0,
                'due_date' => null, 'is_postponed' => false, 'is_active' => true,
            ],
            // Pacific Star — inactive, excluded regardless of how overdue it looks.
            [
                'vessel_id' => $pacificStar->id,
                'activity_name' => 'Retired activity',
                'unit' => 'D', 'min_count_interval' => 0, 'max_count_interval' => 0,
                'no_of_hours' => 0, 'since_delivery' => null,
                'due_date' => now()->subDays(200), 'is_postponed' => false, 'is_active' => false,
            ],
            // Coral Voyager — date-based but no due_date recorded yet: skipped entirely.
            [
                'vessel_id' => $coralVoyager->id,
                'activity_name' => 'Not yet scheduled activity',
                'unit' => 'D', 'min_count_interval' => 0, 'max_count_interval' => 0,
                'no_of_hours' => 0, 'since_delivery' => null,
                'due_date' => null, 'is_postponed' => false, 'is_active' => true,
            ],
            // Coral Voyager — running-hours-based but far from due in either direction.
            [
                'vessel_id' => $coralVoyager->id,
                'activity_name' => 'Far-future running-hours activity',
                'unit' => 'M', 'min_count_interval' => 0, 'max_count_interval' => 6,
                'no_of_hours' => 0, 'since_delivery' => 1,
                'due_date' => null, 'is_postponed' => false, 'is_active' => true,
            ],
        ];

        foreach ($activities as $activity) {
            PmsActivity::updateOrCreate(
                ['vessel_id' => $activity['vessel_id'], 'activity_name' => $activity['activity_name']],
                $activity,
            );
        }
    }
}
