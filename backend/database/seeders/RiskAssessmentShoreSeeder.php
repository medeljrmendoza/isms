<?php

namespace Database\Seeders;

use App\Models\RiskAssessment\RiskAssessmentShore;
use App\Models\RiskAssessment\RiskCategoryShore;
use App\Models\RiskAssessment\RiskOperationShore;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class RiskAssessmentShoreSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $officeWork = RiskCategoryShore::firstOrCreate(['name' => 'Office Work']);
        $warehouseOps = RiskCategoryShore::firstOrCreate(['name' => 'Warehouse Operations']);
        $siteVisit = RiskOperationShore::firstOrCreate(['name' => 'Site Visit']);
        $cargoHandling = RiskOperationShore::firstOrCreate(['name' => 'Cargo Handling']);

        $reports = [
            // --- SHORE-type: office-based, no vessel, both tracks pending, in-progress ---
            [
                'report_no' => 'RAS-2026-001',
                'report_type' => 'SHORE',
                'vessel_id' => null,
                'risk_date' => '2026-07-10',
                'risk_schedule' => '2026-07-12',
                'port' => 'Head Office — Manila',
                'department' => 'HSSE',
                'activity' => 'NON-ROUTINE',
                'risk_category_shore_id' => $warehouseOps->id,
                'risk_operation_shore_id' => $cargoHandling->id,
                'overall_risk' => 'MID',
                'remarks' => 'Warehouse racking inspection ahead of quarterly audit.',
                'approval_from_shore' => true,
                'shore_is_approved' => false,
                'approval_from_marine' => true,
                'marine_is_approved' => false,
                'date_closed' => null,
                'hazards' => [
                    ['unwanted_consequences' => 'Falling stock from racking', 'underlying_causes' => 'Overloaded pallet racks', 'severity' => 3, 'likelihood' => 2, 'risk' => 'LOW', 'existing_control' => 'Load limits posted on each rack bay', 'additional_control' => 'Forklift operators re-briefed on stacking limits', 're_severity' => 3, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['HSSE Officer — Grace Villanueva', 'Warehouse Supervisor — Noel Ibarra'],
            ],

            // --- SHORE-type: OTHER category/task free text, closed report ---
            [
                'report_no' => 'RAS-2026-002',
                'report_type' => 'SHORE',
                'vessel_id' => null,
                'risk_date' => '2026-06-02',
                'risk_schedule' => '2026-06-03',
                'port' => 'Contractor Yard — Batangas',
                'department' => 'PROCUREMENT',
                'activity' => 'NON-ROUTINE',
                'risk_category_shore_id' => null,
                'other_category_name' => 'Third-Party Contractor Audit',
                'risk_operation_shore_id' => null,
                'other_operation_name' => 'Supplier Facility Walkthrough',
                'overall_risk' => 'LOW',
                'remarks' => 'Routine supplier compliance walkthrough, no findings.',
                'approval_from_shore' => true,
                'shore_is_approved' => true,
                'date_approved' => '2026-06-04',
                'shore_remarks' => 'Reviewed and closed out — no outstanding items.',
                'approval_from_marine' => false,
                'date_closed' => '2026-06-05',
                'hazards' => [
                    ['unwanted_consequences' => 'Slip/trip on yard walkway', 'underlying_causes' => 'Uneven pavement near loading bay', 'severity' => 2, 'likelihood' => 2, 'risk' => 'LOW', 'existing_control' => 'Hazard cones placed over damaged section', 'additional_control' => 'Reported to contractor for repair', 're_severity' => 2, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['Procurement Officer — Marites Cua'],
            ],

            // --- VESSEL-type entered by shore on behalf of Pacific Star, real FK category ---
            [
                'report_no' => 'RAS-2026-003',
                'report_type' => 'VESSEL',
                'vessel_id' => $pacificStar->id,
                'risk_date' => '2026-07-18',
                'risk_schedule' => '2026-07-19',
                'port' => 'Port of Colombo',
                'department' => 'DECK',
                'activity' => 'ROUTINE',
                'risk_category_shore_id' => $officeWork->id,
                'risk_operation_shore_id' => $siteVisit->id,
                'overall_risk' => 'MID',
                'remarks' => 'Entered by shore on behalf of vessel — limited connectivity onboard.',
                'approval_from_shore' => true,
                'shore_is_approved' => false,
                'approval_from_marine' => false,
                'date_closed' => null,
                'hazards' => [
                    ['unwanted_consequences' => 'Injury during shore-leave transit', 'underlying_causes' => 'Unlit gangway at night', 'severity' => 3, 'likelihood' => 2, 'risk' => 'LOW', 'existing_control' => 'Portable lighting deployed at gangway', 'additional_control' => 'Watchman posted during crew changes', 're_severity' => 3, 're_likelihood' => 1, 're_risk' => 'LOW'],
                    ['unwanted_consequences' => 'Delay in port clearance', 'underlying_causes' => 'Incomplete documentation on arrival', 'severity' => 2, 'likelihood' => 3, 'risk' => 'MID', 'existing_control' => 'Agent pre-briefed on required documents', 'additional_control' => 'Checklist shared 24h before arrival', 're_severity' => 2, 're_likelihood' => 1, 're_risk' => 'LOW'],
                ],
                'people' => ['Master — Capt. Ramon Alvarez', 'Port Agent — Dilshan Perera'],
            ],

            // --- VESSEL-type for Coral Voyager, no approval required, zero hazards ---
            [
                'report_no' => 'RAS-2026-004',
                'report_type' => 'VESSEL',
                'vessel_id' => $coralVoyager->id,
                'risk_date' => '2026-07-22',
                'risk_schedule' => '2026-07-23',
                'port' => 'Port of Santos',
                'department' => 'ENGINE',
                'activity' => 'ROUTINE',
                'risk_category_shore_id' => $officeWork->id,
                'risk_operation_shore_id' => $siteVisit->id,
                'overall_risk' => null,
                'remarks' => 'Draft entry pending vessel confirmation.',
                'approval_from_shore' => false,
                'approval_from_marine' => false,
                'date_closed' => null,
                'hazards' => [],
                'people' => [],
            ],
        ];

        foreach ($reports as $row) {
            $hazards = $row['hazards'];
            $people = $row['people'];
            unset($row['hazards'], $row['people']);

            $report = RiskAssessmentShore::updateOrCreate(['report_no' => $row['report_no']], $row);

            $report->hazards()->delete();
            foreach ($hazards as $i => $hazard) {
                $report->hazards()->create([...$hazard, 'arrangement' => $i + 1]);
            }

            $report->people()->delete();
            foreach ($people as $i => $personDetails) {
                $report->people()->create(['arrangement' => $i + 1, 'person_details' => $personDetails]);
            }
        }
    }
}
