<?php

namespace Database\Seeders;

use App\Models\Drills\DrillList;
use App\Models\Drills\DrillReport;
use App\Models\Drills\DrillReportCrew;
use App\Models\Vessel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Extends the dashboard-phase drill lists with drill_type + several
 * months of full-record reports (crew, details, deficiencies,
 * corrective action, shore annotation) so the calendar grid has real
 * cells to click through, not just a single latest-report row.
 *
 * DrillReport rows are cleared and recreated rather than updateOrCreate:
 * the dashboard-phase seeder kept exactly one row per drill_list/vessel
 * pair, but the calendar needs several dated reports per pair, so that
 * key stops being a safe upsert target. Reports keep the exact
 * days-ago offsets the dashboard-phase seeder used for each pair's
 * *latest* report — that's what its overdue/upcoming dashlet status
 * depends on — with earlier historical reports added around them purely
 * to populate extra calendar months.
 */
class DrillSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $fireDrill = DrillList::firstOrCreate(
            ['name' => 'Fire Drill'],
            ['drill_type' => 'FIRE', 'frequency_type' => 'M', 'frequency_count' => 1, 'applies_to_all_vessels' => true, 'is_active' => true],
        );
        $fireDrill->update(['drill_type' => 'FIRE']);

        $abandonShipDrill = DrillList::firstOrCreate(
            ['name' => 'Abandon Ship Drill'],
            ['drill_type' => 'ABANDON SHIP', 'frequency_type' => 'M', 'frequency_count' => 1, 'applies_to_all_vessels' => true, 'is_active' => true],
        );
        $abandonShipDrill->update(['drill_type' => 'ABANDON SHIP']);

        // Restricted to Pacific Star only — exercises the vessel_access pivot branch.
        $lifeboatDrill = DrillList::firstOrCreate(
            ['name' => 'Lifeboat Drill'],
            ['drill_type' => 'LIFEBOAT', 'frequency_type' => 'W', 'frequency_count' => 2, 'applies_to_all_vessels' => false, 'is_active' => true],
        );
        $lifeboatDrill->update(['drill_type' => 'LIFEBOAT']);
        $lifeboatDrill->vessels()->syncWithoutDetaching([$pacificStar->id]);

        $manOverboardDrill = DrillList::firstOrCreate(
            ['name' => 'Man Overboard Drill'],
            ['drill_type' => 'MAN OVERBOARD', 'frequency_type' => 'M', 'frequency_count' => 1, 'applies_to_all_vessels' => true, 'is_active' => true],
        );
        $manOverboardDrill->update(['drill_type' => 'MAN OVERBOARD']);

        // Inactive — should be excluded even though it has an overdue-looking report.
        $inactiveDrill = DrillList::firstOrCreate(
            ['name' => 'Retired Drill'],
            ['drill_type' => 'OTHER', 'frequency_type' => 'M', 'frequency_count' => 1, 'applies_to_all_vessels' => true, 'is_active' => false],
        );
        $inactiveDrill->update(['drill_type' => 'OTHER']);

        DrillReport::query()->delete();

        $this->seedReport($fireDrill->id, $pacificStar->id, now()->subDays(105), 'Capt. R. Alonzo', ['2/O', 'Bosun', 'AB Seaman']);
        $this->seedReport($fireDrill->id, $pacificStar->id, now()->subDays(75), 'Capt. R. Alonzo', ['2/O', 'Bosun', 'OS']);
        // Latest — overdue (45 days ago, monthly).
        $this->seedReport($fireDrill->id, $pacificStar->id, now()->subDays(45), 'Capt. R. Alonzo', ['2/O', 'Bosun', 'AB Seaman'], deficiencies: 'One extinguisher past hydro-test date.');

        $this->seedReport($abandonShipDrill->id, $pacificStar->id, now()->subDays(65), 'Capt. R. Alonzo', ['C/O', 'Bosun']);
        // Latest — upcoming (5 days ago, monthly).
        $this->seedReport($abandonShipDrill->id, $pacificStar->id, now()->subDays(5), 'Capt. R. Alonzo', ['C/O', 'Bosun', 'AB Seaman']);

        $this->seedReport($lifeboatDrill->id, $pacificStar->id, now()->subDays(130), 'Capt. R. Alonzo', ['2/O', 'AB Seaman']);
        // Latest — overdue (biweekly, 100 days ago).
        $this->seedReport($lifeboatDrill->id, $pacificStar->id, now()->subDays(100), 'Capt. R. Alonzo', ['2/O', 'AB Seaman'], deficiencies: 'Lowering mechanism stiff on port side.');

        $this->seedReport($manOverboardDrill->id, $pacificStar->id, now()->subDays(20), 'Capt. R. Alonzo', ['3/O', 'AB Seaman']);

        // Excluded — drill list itself is inactive.
        $this->seedReport($inactiveDrill->id, $pacificStar->id, now()->subDays(90), 'Capt. R. Alonzo', ['2/O']);

        $this->seedReport($fireDrill->id, $coralVoyager->id, now()->subDays(70), 'Capt. E. Navarro', ['C/O', 'Bosun']);
        // Latest — upcoming (10 days ago, monthly).
        $this->seedReport($fireDrill->id, $coralVoyager->id, now()->subDays(10), 'Capt. E. Navarro', ['C/O', 'Bosun', 'OS']);

        // Coral Voyager: no Abandon Ship report at all — excluded entirely (matches legacy's "no baseline yet" skip).
    }

    /** @param array<int, string> $crew */
    private function seedReport(
        int $drillListId,
        int $vesselId,
        Carbon $drillDate,
        string $masterName,
        array $crew,
        ?string $deficiencies = null,
    ): void {
        $report = DrillReport::create([
            'drill_list_id' => $drillListId,
            'vessel_id' => $vesselId,
            'master_name' => $masterName,
            'drill_date' => $drillDate->toDateString(),
            'drill_time_from' => '10:00 AM',
            'drill_position' => 'Muster Station',
            'drill_details' => 'Crew mustered and drill conducted per SMS procedure.',
            'drill_deficiencies' => $deficiencies,
            'drill_corrective_action' => $deficiencies ? 'Reported to shore for spares/repair follow-up.' : null,
            'report_date' => $drillDate->copy()->addDay()->toDateString(),
            'vessel_remarks' => 'Drill completed without incident.',
            'receipt_date' => $drillDate->copy()->addDays(3)->toDateString(),
            'shore_remarks' => $deficiencies ? '' : 'Reviewed, no action needed.',
        ]);

        foreach (array_values($crew) as $index => $name) {
            DrillReportCrew::create([
                'drill_report_id' => $report->id,
                'crew_name' => $name,
                'arrangement' => $index,
            ]);
        }
    }
}
