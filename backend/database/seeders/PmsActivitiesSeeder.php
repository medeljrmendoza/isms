<?php

namespace Database\Seeders;

use App\Models\Pms\PmsActivity;
use App\Models\Pms\PmsCriticality;
use App\Models\Pms\PmsDepartment;
use App\Models\Pms\PmsEquipment;
use App\Models\Pms\PmsTicket;
use App\Models\Pms\SpectecMainGroup;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Enriches the PmsActivity rows already seeded by PmsSeeder/
 * PmsRunningHoursSeeder (matched on their same vessel_id+activity_name
 * keys, so the dashlet's summary counts stay untouched) with the fields
 * the full PMS Activities module needs.
 */
class PmsActivitiesSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);

        $deck = PmsDepartment::firstOrCreate(['name' => 'Deck']);
        $engine = PmsDepartment::firstOrCreate(['name' => 'Engine']);
        $safety = PmsDepartment::firstOrCreate(['name' => 'Safety']);

        $critical = PmsCriticality::firstOrCreate(['name' => 'Critical']);
        $nonCritical = PmsCriticality::firstOrCreate(['name' => 'Non-Critical']);

        $machinery = SpectecMainGroup::firstOrCreate(['code' => '2'], ['name' => 'Machinery']);
        $hull = SpectecMainGroup::firstOrCreate(['code' => '1'], ['name' => 'Hull & Deck']);
        $safetyGroup = SpectecMainGroup::firstOrCreate(['code' => '6'], ['name' => 'Safety Equipment']);

        PmsEquipment::where('equipment_code', 'ME-01')->update(['criticality_id' => $critical->id]);
        PmsEquipment::where('equipment_code', 'AE-01')->update(['criticality_id' => $critical->id]);
        PmsEquipment::where('equipment_code', 'SG-01')->update(['criticality_id' => $critical->id]);

        $enrichments = [
            'Overdue date-based activity' => [
                'activity_code' => '1.05', 'pms_department_id' => $deck->id, 'spectec_main_group_id' => $hull->id,
                'incharge' => 'Bosun', 'work_procedure' => 'Inspect hull markings and touch up as required.',
                'previous_due_date' => now()->subDays(40),
            ],
            'Upcoming date-based activity' => [
                'activity_code' => '6.02', 'pms_department_id' => $safety->id, 'spectec_main_group_id' => $safetyGroup->id,
                'incharge' => 'Safety Officer', 'work_procedure' => 'Service and re-certify fire extinguishers.',
                'last_done' => now()->subMonths(11), 'previous_due_date' => now()->subMonths(11)->addDays(20),
            ],
            'Overdue running-hours activity' => [
                'activity_code' => '2.11', 'pms_department_id' => $engine->id, 'spectec_main_group_id' => $machinery->id,
                'incharge' => 'Chief Engineer', 'work_procedure' => 'Replace lube oil filter elements.',
            ],
            'Upcoming running-hours activity' => [
                'activity_code' => '2.12', 'pms_department_id' => $engine->id, 'spectec_main_group_id' => $machinery->id,
                'incharge' => '2nd Engineer', 'work_procedure' => 'Inspect and clean turbocharger.',
            ],
            'Postponed activity' => [
                'activity_code' => '1.09', 'pms_department_id' => $deck->id, 'spectec_main_group_id' => $hull->id,
                'incharge' => 'Bosun', 'work_procedure' => 'Renew deck non-skid coating.',
            ],
            'Main Engine Top Overhaul' => [
                'activity_code' => '2.01', 'pms_department_id' => $engine->id, 'spectec_main_group_id' => $machinery->id,
                'incharge' => 'Chief Engineer', 'work_procedure' => 'Full top overhaul per maker manual.',
                'last_done' => now()->subMonths(3),
            ],
        ];

        foreach ($enrichments as $activityName => $fields) {
            PmsActivity::where('vessel_id', $pacificStar->id)->where('activity_name', $activityName)->update($fields);
        }

        // Sample ticket log: one PLANNED and one POSTPONED entry with
        // monthly cells wired to match, so "View Ticket" resolves.
        $overhaul = PmsActivity::where('vessel_id', $pacificStar->id)->where('activity_name', 'Main Engine Top Overhaul')->first();
        $postponed = PmsActivity::where('vessel_id', $pacificStar->id)->where('activity_name', 'Postponed activity')->first();

        if ($overhaul) {
            $doneMonth = now()->subMonths(3);
            $ticketNo = 'PLANNED-MVPacificStar-'.$doneMonth->year.'-1';

            $overhaul->update(['monthly_done' => [(string) $doneMonth->month => ['day' => $doneMonth->day, 'ticket_no' => $ticketNo, 'is_overdue' => false]]]);

            PmsTicket::updateOrCreate(['ticket_no' => $ticketNo], [
                'vessel_id' => $pacificStar->id,
                'pms_activity_id' => $overhaul->id,
                'type' => 'PLANNED',
                'activity_name' => $overhaul->activity_name,
                'date_of_activity' => $doneMonth,
                'description' => null,
                'possible_cause' => null,
                'remarks' => 'Completed during scheduled port call.',
                'incharge' => $overhaul->incharge,
                'min_count_interval' => $overhaul->min_count_interval,
                'max_count_interval' => $overhaul->max_count_interval,
                'unit' => $overhaul->unit,
                'is_overdue' => false,
                'equipment_name' => 'Main Engine',
                'reported_by' => 'C. Reyes',
            ]);
        }

        if ($postponed) {
            $postponeDate = now();
            $ticketNo = 'POSTPONED-MVPacificStar-'.$postponeDate->year.'-1';

            $postponed->update([
                'is_postponed' => true,
                'postpone_date' => $postponeDate,
                'monthly_postponed' => [(string) $postponeDate->month => ['day' => $postponeDate->day, 'ticket_no' => $ticketNo]],
            ]);

            PmsTicket::updateOrCreate(['ticket_no' => $ticketNo], [
                'vessel_id' => $pacificStar->id,
                'pms_activity_id' => $postponed->id,
                'type' => 'POSTPONED',
                'activity_name' => $postponed->activity_name,
                'date_of_activity' => $postponeDate,
                'description' => 'Deck team unavailable — occupied with cargo operations.',
                'possible_cause' => 'Schedule conflict with cargo watch.',
                'remarks' => null,
                'incharge' => $postponed->incharge,
                'min_count_interval' => $postponed->min_count_interval,
                'max_count_interval' => $postponed->max_count_interval,
                'unit' => $postponed->unit,
            ]);
        }
    }
}
