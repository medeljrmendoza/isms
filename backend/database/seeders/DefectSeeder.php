<?php

namespace Database\Seeders;

use App\Models\Defect;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class DefectSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $defects = [
            // Should appear: open defect.
            [
                'vessel_id' => $pacificStar->id,
                'sl_no' => 'DEF-001',
                'defect_date' => now()->subDays(5),
                'priority' => 'HIGH',
                'category' => 'DECK',
                'compl_code' => 'O',
                'description' => 'Bridge radar intermittent fault.',
            ],
            // Should appear: pending defect.
            [
                'vessel_id' => $coralVoyager->id,
                'sl_no' => 'DEF-002',
                'defect_date' => now()->subDays(2),
                'priority' => 'MEDIUM',
                'category' => 'ENGINE',
                'compl_code' => 'P',
                'description' => 'Auxiliary generator vibration above normal.',
            ],
            // Should NOT appear: already completed (compl_code = 'C').
            [
                'vessel_id' => $pacificStar->id,
                'sl_no' => 'DEF-003',
                'defect_date' => now()->subDays(20),
                'priority' => 'LOW',
                'category' => 'SAFETY',
                'compl_code' => 'C',
                'description' => 'Lifeboat davit paint touch-up.',
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
