<?php

namespace Database\Seeders;

use App\Models\IncidentLocation;
use App\Models\IncidentOperation;
use App\Models\IncidentPersonParticipated;
use App\Models\IncidentReport;
use App\Models\IncidentRootCause;
use App\Models\LocationOfInjury;
use App\Models\NatureOfIncident;
use App\Models\RootCause;
use App\Models\TypeOfInjury;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class IncidentReportSeeder extends Seeder
{
    public function run(): void
    {
        // updateOrCreate matching on a `date`-cast column is unreliable across
        // re-runs (Eloquent doesn't normalize the match value before querying),
        // so this seeder clears its own rows first rather than trying to
        // upsert — cascades to incident_root_causes/incident_persons_participated.
        IncidentReport::query()->delete();

        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $fire = NatureOfIncident::firstOrCreate(['name' => 'Fire']);
        $collision = NatureOfIncident::firstOrCreate(['name' => 'Collision']);
        $grounding = NatureOfIncident::firstOrCreate(['name' => 'Grounding']);
        $other = NatureOfIncident::firstOrCreate(['name' => 'Other']);

        $rows = [
            // --- Should appear ---
            // Fully filled out accident report — exercises particulars, weather,
            // personnel counts, injury/damage sections, root cause + persons sub-rows.
            [
                'vessel_id' => $pacificStar->id,
                'dateof_report' => '2026-07-08',
                'voyage_no' => 'V-2026-014',
                'report_no' => 'INC-2026-001',
                'master_name' => 'Capt. R. Alonzo',
                'chief_engineer_name' => 'C/E J. Domingo',
                'person_reporting' => 'Capt. R. Alonzo',
                'added_by' => 'SHORE',
                'published' => true,
                'nature_type' => 'accident',
                'nature_of_incident_id' => $fire->id,
                'statementof_work' => 'Small electrical fire in the engine room, extinguished quickly by crew.',
                'bac' => 'NO',
                'vdr' => 'YES',
                'dateof_event' => '2026-07-08',
                'timeof_event' => '14:30',
                'zone' => 'Zone 3',
                'country' => 'Singapore',
                'geographical_location' => 'Singapore Strait',
                'atmospheric_clear' => true,
                'sea1' => true,
                'crew_onboard' => 22,
                'total_onboard' => 22,
                'crew_injured' => 1,
                'total_injured' => 1,
                'fs_ro' => 'YES',
                'severity_itp' => 'MTC',
                'comment_itp' => 'Minor smoke inhalation, treated onboard.',
                'location_of_injury_id' => LocationOfInjury::where('body_part', 'Torso')->value('id'),
                'type_of_injury_id' => TypeOfInjury::where('name', 'Burn')->value('id'),
                'severity_itv' => 'low',
                'comment_itv' => 'Minor scorching to a cable tray.',
                'signed_by' => 'Capt. R. Alonzo',
                'date_signed' => '2026-07-09',
                'date_received' => '2026-07-10',
                'reviewed_by' => 'M. Santos',
                'investigator' => 'M. Santos',
                'dpa' => 'M. Santos',
                'closing_date' => null,
                'is_approved' => false,
            ],
            [
                'vessel_id' => $coralVoyager->id,
                'dateof_report' => '2026-06-25',
                'added_by' => 'SHORE',
                'published' => false,
                'nature_type' => 'accident',
                'nature_of_incident_id' => $collision->id,
                'accident_collision' => 'Contact with pier fendering',
                'statementof_work' => 'Vessel made light contact with pier fendering during berthing.',
                'closing_date' => '2026-06-30',
                'is_approved' => false,
            ],
            // Fully filled out hazardous occurrence — exercises HOR-only sections.
            [
                'vessel_id' => $pacificStar->id,
                'dateof_report' => '2026-07-11',
                'voyage_no' => 'V-2026-015',
                'report_no' => 'INC-2026-002',
                'person_reporting' => 'C/O D. Reyes',
                'added_by' => 'VESSEL',
                'published' => true,
                'nature_type' => 'hazardous_occurrence',
                'hazardous_occurrence_type' => 'near_miss',
                'statementof_work' => 'Crew member nearly struck by swinging cargo hook.',
                'incident_location_id' => IncidentLocation::where('name', 'Cargo Hold')->value('id'),
                'ship_position' => 'Alongside, Berth 4',
                'incident_operation_id' => IncidentOperation::where('name', 'Cargo Operations')->value('id'),
                'hazardous_occurrence_ppe_used' => 'YES',
                'hazardous_occurrence_ppe_used_comment' => 'Hard hat and safety boots worn.',
                'hazardous_occurrence_severity' => 'MEDIUM',
                'hazardous_occurrence_likelihood' => 'LOW',
                'subject_investigation' => 'YES',
                'evidence_safety_meeting' => true,
                'evidence_photo' => true,
                'causal_factor' => 'Hook not secured during pause in operations.',
                'intermediate_cause' => 'Crew stood within swing radius.',
                'shore_root_cause_summary' => 'Lifting operation procedure not fully followed.',
                'signed_by' => 'C/O D. Reyes',
                'date_signed' => '2026-07-11',
                'date_received' => '2026-07-12',
                'closing_date' => null,
                'is_approved' => false,
            ],
            [
                'vessel_id' => $coralVoyager->id,
                'dateof_report' => '2026-07-03',
                'added_by' => 'SHORE',
                'published' => false,
                'nature_type' => 'accident',
                'nature_of_incident_id' => $other->id,
                'others' => 'Minor slip on deck',
                'statementof_work' => 'Crew member slipped on wet deck near amidships.',
                'closing_date' => null,
                'is_approved' => false,
            ],

            // --- Should NOT appear ---
            [
                'vessel_id' => $pacificStar->id,
                'dateof_report' => '2026-05-01',
                'nature_type' => 'accident',
                'nature_of_incident_id' => $grounding->id,
                'closing_date' => '2026-05-20',
                'is_approved' => true,
            ],
            [
                'vessel_id' => $coralVoyager->id,
                'dateof_report' => '2026-05-10',
                'nature_type' => 'hazardous_occurrence',
                'hazardous_occurrence_type' => 'unsafe_condition',
                'closing_date' => '2026-05-15',
                'is_approved' => true,
            ],
        ];

        foreach ($rows as $row) {
            IncidentReport::create($row);
        }

        // Root cause + persons-participated sub-rows for the fully-detailed accident report.
        $fireReport = IncidentReport::where('vessel_id', $pacificStar->id)->whereDate('dateof_report', '2026-07-08')->first();

        if ($fireReport) {
            IncidentRootCause::create([
                'incident_report_id' => $fireReport->id,
                'arrangement' => 0,
                'root_cause_id' => RootCause::where('name', 'Inadequate Maintenance')->value('id'),
                'investigation' => 'Reviewed maintenance logs for the affected panel.',
                'analysis' => 'Panel was overdue for scheduled inspection.',
                'corrective_actions' => 'Bring forward inspection schedule for all similar panels fleet-wide.',
            ]);
            IncidentRootCause::create([
                'incident_report_id' => $fireReport->id,
                'arrangement' => 1,
                'root_cause_id' => RootCause::where('name', 'Other')->value('id'),
                'root_cause_other' => 'Non-standard replacement part previously fitted',
                'investigation' => 'Traced the part back to prior repair records.',
                'analysis' => 'A non-OEM part was fitted during a prior repair.',
                'corrective_actions' => 'Replace with OEM part and update spares procurement policy.',
            ]);

            IncidentPersonParticipated::create([
                'incident_report_id' => $fireReport->id,
                'arrangement' => 0,
                'person_name' => 'AB J. Cruz',
                'position' => 'Able Seaman',
            ]);
            IncidentPersonParticipated::create([
                'incident_report_id' => $fireReport->id,
                'arrangement' => 1,
                'person_name' => 'C/E J. Domingo',
                'position' => 'Chief Engineer',
            ]);
        }
    }
}
