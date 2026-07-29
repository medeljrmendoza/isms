<?php

namespace Database\Seeders;

use App\Models\RiskAssessment\RiskAssessment;
use App\Models\RiskAssessment\RiskCategory;
use App\Models\RiskAssessment\RiskOperation;
use App\Models\Sire\SireReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class RiskAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $enclosedSpace = RiskCategory::firstOrCreate(['name' => 'Enclosed Space Entry']);
        $hotWork = RiskCategory::firstOrCreate(['name' => 'Hot Work']);
        $mooring = RiskOperation::firstOrCreate(['name' => 'Mooring Operations']);
        $anchoring = RiskOperation::firstOrCreate(['name' => 'Anchoring Operations']);

        $riskRows = [
            // --- Dashboard pending + full list: shore approval required, not yet approved ---
            [
                'report_no' => 'RA-2026-001',
                'vessel_id' => $pacificStar->id,
                'risk_date' => '2026-07-06',
                'risk_schedule' => '2026-07-08',
                'port' => 'Port of Rotterdam',
                'department' => 'DECK',
                'activity' => 'NON-ROUTINE',
                'risk_category_id' => null,
                'other_category_name' => 'Custom Lifting Task',
                'risk_operation_id' => $mooring->id,
                'overall_risk' => 'MID',
                'master' => 'Capt. Ramon Alvarez',
                'ce_co' => 'C/O Ben Sarmiento',
                'vessel_remarks' => 'Heavy lift plan reviewed with deck crew prior to task.',
                'approval_from_shore' => true,
                'shore_is_approved' => false,
                'approval_from_marine' => false,
                'marine_is_approved' => false,
                'hazards' => [
                    ['unwanted_consequences' => 'Crew injury from dropped load', 'underlying_causes' => 'Improper rigging of lifting gear', 'severity' => 4, 'likelihood' => 2, 'risk' => 'MID', 'existing_control' => 'Certified rigging equipment inspected before use', 'additional_control' => 'Toolbox talk conducted, exclusion zone marked', 're_severity' => 4, 're_likelihood' => 1, 're_risk' => 'LOW'],
                    ['unwanted_consequences' => 'Damage to deck equipment', 'underlying_causes' => 'Load swinging in poor weather', 'severity' => 3, 'likelihood' => 2, 'risk' => 'LOW', 'existing_control' => 'Weather forecast checked before operation', 'additional_control' => 'Tag lines used to control load', 're_severity' => 3, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['Master — Capt. Ramon Alvarez', 'Chief Officer — Ben Sarmiento', 'Bosun — Ricardo Cruz'],
            ],

            // --- Dashboard pending + full list: marine approval required, not yet approved ---
            [
                'report_no' => 'RA-2026-002',
                'vessel_id' => $coralVoyager->id,
                'risk_date' => '2026-07-09',
                'risk_schedule' => '2026-07-10',
                'port' => 'Port of Singapore',
                'department' => 'ENGINE',
                'activity' => 'ROUTINE',
                'risk_category_id' => $enclosedSpace->id,
                'risk_operation_id' => null,
                'other_operation_name' => 'Custom Bunkering Task',
                'overall_risk' => 'HIGH',
                'master' => 'Capt. Elena Reyes',
                'ce_co' => 'C/E Miguel Torres',
                'vessel_remarks' => 'Bunkering checklist completed, spill kit staged on deck.',
                'approval_from_shore' => false,
                'approval_from_marine' => true,
                'marine_is_approved' => false,
                'hazards' => [
                    ['unwanted_consequences' => 'Oil spill to sea', 'underlying_causes' => 'Hose connection failure during transfer', 'severity' => 5, 'likelihood' => 2, 'risk' => 'HIGH', 'existing_control' => 'Pre-transfer hose inspection, drip trays in place', 'additional_control' => 'Continuous watch at manifold throughout transfer', 're_severity' => 5, 're_likelihood' => 1, 're_risk' => 'MID'],
                ],
                'people' => ['Chief Engineer — Miguel Torres', '2nd Engineer — Paolo Diaz'],
            ],

            // --- Dashboard: already shore-approved (excluded from pending); full list: approved status visible ---
            [
                'report_no' => 'RA-2026-003',
                'vessel_id' => $pacificStar->id,
                'risk_date' => '2026-06-01',
                'risk_schedule' => '2026-06-03',
                'port' => 'Port of Fujairah',
                'department' => 'DECK',
                'activity' => 'ROUTINE',
                'risk_category_id' => $enclosedSpace->id,
                'risk_operation_id' => $mooring->id,
                'overall_risk' => 'LOW',
                'master' => 'Capt. Ramon Alvarez',
                'ce_co' => 'C/O Ben Sarmiento',
                'vessel_remarks' => 'Enclosed space entry permit completed and atmosphere tested.',
                'approval_from_shore' => true,
                'shore_is_approved' => true,
                'date_approved' => '2026-06-02',
                'shore_remarks' => 'Reviewed and approved — controls adequate.',
                'approval_from_marine' => false,
                'marine_is_approved' => false,
                'hazards' => [
                    ['unwanted_consequences' => 'Crew asphyxiation', 'underlying_causes' => 'Oxygen-deficient atmosphere in tank', 'severity' => 5, 'likelihood' => 1, 'risk' => 'LOW', 'existing_control' => 'Gas testing before and during entry', 'additional_control' => 'Standby man at entrance with rescue harness', 're_severity' => 5, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['Chief Officer — Ben Sarmiento', 'AB — Jomar Santos'],
            ],

            // --- Neither track requires approval: can_edit false even with hazards recorded ---
            [
                'report_no' => 'RA-2026-004',
                'vessel_id' => $coralVoyager->id,
                'risk_date' => '2026-06-05',
                'risk_schedule' => '2026-06-06',
                'port' => 'Port of Santos',
                'department' => 'DECK',
                'activity' => 'ROUTINE',
                'risk_category_id' => $enclosedSpace->id,
                'risk_operation_id' => $mooring->id,
                'overall_risk' => 'LOW',
                'master' => 'Capt. Elena Reyes',
                'ce_co' => 'C/O Anna Bautista',
                'vessel_remarks' => 'Standard mooring operation, no deviations noted.',
                'approval_from_shore' => false,
                'approval_from_marine' => false,
                'hazards' => [
                    ['unwanted_consequences' => 'Crew injury from parted mooring line', 'underlying_causes' => 'Line under excessive tension', 'severity' => 3, 'likelihood' => 2, 'risk' => 'LOW', 'existing_control' => 'Snap-back zones marked and enforced', 'additional_control' => null, 're_severity' => 3, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['Chief Officer — Anna Bautista'],
            ],

            // --- Shore approval required but zero hazards recorded: can_edit stays false ---
            [
                'report_no' => 'RA-2026-005',
                'vessel_id' => $pacificStar->id,
                'risk_date' => '2026-07-15',
                'risk_schedule' => '2026-07-16',
                'port' => 'Port of Colombo',
                'department' => 'DECK',
                'activity' => 'NON-ROUTINE',
                'risk_category_id' => $hotWork->id,
                'risk_operation_id' => $anchoring->id,
                'overall_risk' => null,
                'master' => 'Capt. Ramon Alvarez',
                'ce_co' => 'C/O Ben Sarmiento',
                'vessel_remarks' => 'Assessment table not yet completed by vessel.',
                'approval_from_shore' => true,
                'shore_is_approved' => false,
                'approval_from_marine' => false,
                'hazards' => [],
                'people' => [],
            ],

            // --- Both tracks required: shore approved, marine still pending ---
            [
                'report_no' => 'RA-2026-006',
                'vessel_id' => $coralVoyager->id,
                'risk_date' => '2026-07-20',
                'risk_schedule' => '2026-07-22',
                'port' => 'Port of Fujairah',
                'department' => 'ENGINE',
                'activity' => 'NON-ROUTINE',
                'risk_category_id' => $hotWork->id,
                'risk_operation_id' => null,
                'other_operation_name' => 'Emergency Generator Welding Repair',
                'overall_risk' => 'HIGH',
                'master' => 'Capt. Elena Reyes',
                'ce_co' => 'C/E Miguel Torres',
                'vessel_remarks' => 'Hot work permit issued, fire watch posted.',
                'approval_from_shore' => true,
                'shore_is_approved' => true,
                'date_approved' => '2026-07-21',
                'shore_remarks' => 'Approved subject to continuous fire watch.',
                'approval_from_marine' => true,
                'marine_is_approved' => false,
                'hazards' => [
                    ['unwanted_consequences' => 'Fire in engine room', 'underlying_causes' => 'Welding sparks near fuel lines', 'severity' => 5, 'likelihood' => 2, 'risk' => 'HIGH', 'existing_control' => 'Fire watch posted, extinguishers staged', 'additional_control' => 'Fuel lines shielded and area cleared of combustibles', 're_severity' => 5, 're_likelihood' => 1, 're_risk' => 'MID'],
                    ['unwanted_consequences' => 'Crew burns', 'underlying_causes' => 'Inadequate PPE during welding', 'severity' => 3, 'likelihood' => 2, 'risk' => 'LOW', 'existing_control' => 'Full PPE mandatory for welding crew', 'additional_control' => null, 're_severity' => 3, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['Chief Engineer — Miguel Torres', 'Fitter — Danilo Reyes', 'Fire Watch — Paolo Diaz'],
            ],

            // --- Different year: excluded when filtering the module list by 2026 ---
            [
                'report_no' => 'RA-2025-014',
                'vessel_id' => $pacificStar->id,
                'risk_date' => '2025-11-12',
                'risk_schedule' => '2025-11-13',
                'port' => 'Port of Jebel Ali',
                'department' => 'DECK',
                'activity' => 'ROUTINE',
                'risk_category_id' => $enclosedSpace->id,
                'risk_operation_id' => $mooring->id,
                'overall_risk' => 'LOW',
                'master' => 'Capt. Ramon Alvarez',
                'ce_co' => 'C/O Ben Sarmiento',
                'vessel_remarks' => 'Prior-year report, retained for year-filter coverage.',
                'approval_from_shore' => true,
                'shore_is_approved' => true,
                'date_approved' => '2025-11-13',
                'shore_remarks' => 'Approved.',
                'approval_from_marine' => false,
                'hazards' => [
                    ['unwanted_consequences' => 'Crew injury during mooring', 'underlying_causes' => 'Line handling in confined space', 'severity' => 3, 'likelihood' => 1, 'risk' => 'LOW', 'existing_control' => 'Snap-back zones enforced', 'additional_control' => null, 're_severity' => 3, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['Chief Officer — Ben Sarmiento'],
            ],
        ];

        foreach ($riskRows as $row) {
            $hazards = $row['hazards'];
            $people = $row['people'];
            unset($row['hazards'], $row['people']);

            $report = RiskAssessment::updateOrCreate(['report_no' => $row['report_no']], $row);

            $report->hazards()->delete();
            foreach ($hazards as $i => $hazard) {
                $report->hazards()->create([...$hazard, 'arrangement' => $i + 1]);
            }

            $report->people()->delete();
            foreach ($people as $i => $personDetails) {
                $report->people()->create(['arrangement' => $i + 1, 'person_details' => $personDetails]);
            }
        }

        $sireRows = [
            // --- Should appear ---
            [
                'vessel_id' => $pacificStar->id,
                'dateof_inspection' => '2026-07-07',
                'placeof_inspection' => 'Port of Rotterdam',
                'is_published' => true,
                'is_approved' => false,
                'is_deleted' => false,
            ],

            // --- Should NOT appear ---
            [
                'vessel_id' => $coralVoyager->id,
                'dateof_inspection' => '2026-06-10',
                'placeof_inspection' => 'Port of Singapore',
                'is_published' => true,
                'is_approved' => true,
                'is_deleted' => false,
            ],
            [
                'vessel_id' => $pacificStar->id,
                'dateof_inspection' => '2026-07-01',
                'placeof_inspection' => 'Port of Fujairah — unpublished draft',
                'is_published' => false,
                'is_approved' => false,
                'is_deleted' => false,
            ],
            [
                'vessel_id' => $coralVoyager->id,
                'dateof_inspection' => '2026-05-15',
                'placeof_inspection' => 'Port of Santos — deleted',
                'is_published' => true,
                'is_approved' => false,
                'is_deleted' => true,
            ],
        ];

        foreach ($sireRows as $row) {
            SireReport::updateOrCreate(
                ['vessel_id' => $row['vessel_id'], 'dateof_inspection' => $row['dateof_inspection']],
                $row,
            );
        }
    }
}
