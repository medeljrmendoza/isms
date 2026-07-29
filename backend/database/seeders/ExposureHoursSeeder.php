<?php

namespace Database\Seeders;

use App\Models\ExposureHours\ExposureHoursRecord;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Two non-overlapping records per vessel (older + newer) to prove
 * "latest per vessel" picks the newer one on the dashboard dashlet, plus
 * a vessel with zero records to prove it's correctly excluded there.
 * Periods must not overlap — the app now enforces that the same way
 * legacy's add_record() does — so this also doubles as the fixture the
 * Records page's overlap-rejection check gets tested against.
 *
 * Cleared and recreated rather than updateOrCreate: matching on
 * date_from (a date-cast column) is unreliable across re-runs in
 * SQLite, same landmine fixed in IncidentReportSeeder/SireSeeder/etc.
 */
class ExposureHoursSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $pacificStar->update(['max_crew' => 26]);

        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);
        $coralVoyager->update(['max_crew' => 24]);

        $northernLight = Vessel::firstOrCreate(['name' => 'Northern Light'], ['prefix' => 'MV']);
        $northernLight->update(['max_crew' => 20]);

        ExposureHoursRecord::query()->delete();

        $records = [
            // Pacific Star — older record (should be superseded on the dashlet).
            [
                'vessel_id' => $pacificStar->id,
                'added_by' => 'SHORE',
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
                'shore_remarks' => 'Q1 report reviewed.',
            ],
            // Pacific Star — latest record (should win on the dashlet).
            [
                'vessel_id' => $pacificStar->id,
                'added_by' => 'SHORE',
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
                'shore_remarks' => 'Q2 report reviewed.',
            ],
            // Coral Voyager — older record.
            [
                'vessel_id' => $coralVoyager->id,
                'added_by' => 'SHORE',
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
                'shore_remarks' => 'No incidents this period.',
            ],
            // Coral Voyager — latest record (should win on the dashlet).
            [
                'vessel_id' => $coralVoyager->id,
                'added_by' => 'SHORE',
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
                'shore_remarks' => '',
            ],
        ];

        foreach ($records as $record) {
            ExposureHoursRecord::create($record);
        }
    }
}
