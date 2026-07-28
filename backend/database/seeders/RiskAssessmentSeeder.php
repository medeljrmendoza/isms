<?php

namespace Database\Seeders;

use App\Models\RiskAssessment;
use App\Models\RiskCategory;
use App\Models\RiskOperation;
use App\Models\SireReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class RiskAssessmentSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $enclosedSpace = RiskCategory::firstOrCreate(['name' => 'Enclosed Space Entry']);
        $mooring = RiskOperation::firstOrCreate(['name' => 'Mooring Operations']);

        $riskRows = [
            // --- Should appear ---
            [
                'report_no' => 'RA-2026-001',
                'vessel_id' => $pacificStar->id,
                'risk_date' => '2026-07-06',
                'risk_category_id' => null,
                'other_category_name' => 'Custom Lifting Task',
                'risk_operation_id' => $mooring->id,
                'approval_from_shore' => true,
                'shore_is_approved' => false,
                'approval_from_marine' => false,
                'marine_is_approved' => false,
            ],
            [
                'report_no' => 'RA-2026-002',
                'vessel_id' => $coralVoyager->id,
                'risk_date' => '2026-07-09',
                'risk_category_id' => $enclosedSpace->id,
                'risk_operation_id' => null,
                'other_operation_name' => 'Custom Bunkering Task',
                'approval_from_shore' => false,
                'approval_from_marine' => true,
                'marine_is_approved' => false,
            ],

            // --- Should NOT appear ---
            [
                'report_no' => 'RA-2026-003',
                'vessel_id' => $pacificStar->id,
                'risk_date' => '2026-06-01',
                'risk_category_id' => $enclosedSpace->id,
                'risk_operation_id' => $mooring->id,
                'approval_from_shore' => true,
                'shore_is_approved' => true,
                'approval_from_marine' => false,
                'marine_is_approved' => false,
            ],
            [
                'report_no' => 'RA-2026-004',
                'vessel_id' => $coralVoyager->id,
                'risk_date' => '2026-06-05',
                'risk_category_id' => $enclosedSpace->id,
                'risk_operation_id' => $mooring->id,
                'approval_from_shore' => false,
                'approval_from_marine' => false,
            ],
        ];

        foreach ($riskRows as $row) {
            RiskAssessment::updateOrCreate(['report_no' => $row['report_no']], $row);
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
