<?php

namespace Database\Seeders;

use App\Models\ExposureHoursRecord;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Two records per vessel (older + newer) to prove "latest per vessel"
 * actually picks the newer one, plus a vessel with zero records to prove
 * it's correctly excluded from the dashlet.
 */
class ExposureHoursSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);
        Vessel::firstOrCreate(['name' => 'Northern Light'], ['prefix' => 'MV']);

        $records = [
            // Pacific Star — older record (should be superseded)
            [
                'vessel_id' => $pacificStar->id,
                'date_from' => '2026-01-01',
                'date_to' => '2026-03-31',
                'no_of_crew' => 22,
                'no_of_fat' => 0,
                'no_of_ptd' => 0,
                'no_of_ppd' => 0,
                'no_of_lwc' => 1,
                'no_of_rwc' => 0,
                'no_of_mtc' => 2,
                'total_hours' => 15840,
            ],
            // Pacific Star — latest record (should win)
            [
                'vessel_id' => $pacificStar->id,
                'date_from' => '2026-04-01',
                'date_to' => '2026-06-30',
                'no_of_crew' => 24,
                'no_of_fat' => 0,
                'no_of_ptd' => 0,
                'no_of_ppd' => 0,
                'no_of_lwc' => 0,
                'no_of_rwc' => 1,
                'no_of_mtc' => 1,
                'total_hours' => 17280,
            ],
            // Coral Voyager — older record
            [
                'vessel_id' => $coralVoyager->id,
                'date_from' => '2026-02-01',
                'date_to' => '2026-04-30',
                'no_of_crew' => 20,
                'no_of_fat' => 0,
                'no_of_ptd' => 0,
                'no_of_ppd' => 0,
                'no_of_lwc' => 0,
                'no_of_rwc' => 0,
                'no_of_mtc' => 0,
                'total_hours' => 14400,
            ],
            // Coral Voyager — latest record (should win)
            [
                'vessel_id' => $coralVoyager->id,
                'date_from' => '2026-05-01',
                'date_to' => '2026-07-15',
                'no_of_crew' => 21,
                'no_of_fat' => 0,
                'no_of_ptd' => 0,
                'no_of_ppd' => 0,
                'no_of_lwc' => 0,
                'no_of_rwc' => 0,
                'no_of_mtc' => 1,
                'total_hours' => 12852,
            ],
        ];

        foreach ($records as $record) {
            ExposureHoursRecord::updateOrCreate(
                ['vessel_id' => $record['vessel_id'], 'date_from' => $record['date_from']],
                $record,
            );
        }
    }
}
