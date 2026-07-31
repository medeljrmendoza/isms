<?php

namespace Database\Seeders;

use App\Models\Defects\Defect;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/** Ported from Controllers/Defect_list.php. */
class DefectSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $defects = [
            // Should appear on the dashlet: open, high-priority defect.
            [
                'vessel_id' => $pacificStar->id,
                'sl_no' => 'DEF-001',
                'defect_date' => now()->subDays(5),
                'priority' => '1',
                'category' => 'N',
                'raised_by' => 'VIR',
                'compl_code' => 'P',
                'description' => 'Bridge radar intermittent fault.',
                'present_status' => 'Awaiting spare part delivery from shore.',
                'expected_compl_date' => now()->addDays(10),
                'shore_remarks' => 'Spare ordered, ETA confirmed with supplier.',
            ],
            // Should appear on the dashlet: in-progress defect.
            [
                'vessel_id' => $coralVoyager->id,
                'sl_no' => 'DEF-002',
                'defect_date' => now()->subDays(2),
                'priority' => '2',
                'category' => 'T',
                'raised_by' => 'VSL',
                'compl_code' => 'I',
                'description' => 'Auxiliary generator vibration above normal.',
                'present_status' => 'Vibration analysis scheduled at next port.',
                'expected_compl_date' => now()->addDays(20),
            ],
            // Should NOT appear on the dashlet: already completed (compl_code = 'C').
            [
                'vessel_id' => $pacificStar->id,
                'sl_no' => 'DEF-003',
                'defect_date' => now()->subDays(20),
                'priority' => '3',
                'category' => 'O',
                'raised_by' => 'IAR',
                'compl_code' => 'C',
                'description' => 'Lifeboat davit paint touch-up.',
                'present_status' => 'Repainted and inspected.',
                'expected_compl_date' => now()->subDays(15),
                'compl_date' => now()->subDays(14),
                'shore_remarks' => 'Closed after satisfactory inspection.',
            ],
            // Full-record example: hot work by shipstaff, no expected/compl dates yet.
            [
                'vessel_id' => $coralVoyager->id,
                'sl_no' => 'DEF-004',
                'defect_date' => now()->subDays(1),
                'priority' => '1',
                'category' => 'N',
                'raised_by' => 'TPR',
                'compl_code' => 'H',
                'description' => 'Steering gear hydraulic hose showing signs of wear.',
                'present_status' => 'Crew monitoring daily pending replacement hose.',
            ],
        ];

        foreach ($defects as $defect) {
            Defect::updateOrCreate(
                ['vessel_id' => $defect['vessel_id'], 'sl_no' => $defect['sl_no']],
                $defect,
            );
        }
    }
}
