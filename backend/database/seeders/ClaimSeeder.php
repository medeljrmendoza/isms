<?php

namespace Database\Seeders;

use App\Models\Claim;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class ClaimSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $rows = [
            // --- Should appear ---
            [
                'claim_no' => 'JPI-2201',
                'claims_category' => 'Cargo Damage',
                'vessel_id' => $pacificStar->id,
                'report_date' => '2026-07-02',
                'status' => 'OPEN',
            ],
            [
                'claim_no' => 'JPI-2202',
                'claims_category' => 'Crew Injury',
                'vessel_id' => $coralVoyager->id,
                'report_date' => '2026-06-28',
                'status' => 'IN PROGRESS',
            ],
            [
                'claim_no' => 'JPI-2203',
                'claims_category' => 'Third-Party Property Damage',
                'vessel_id' => $pacificStar->id,
                'report_date' => '2026-07-10',
                'status' => 'PENDING DOCUMENTATION',
            ],

            // --- Should NOT appear ---
            [
                'claim_no' => 'JPI-2200',
                'claims_category' => 'Cargo Shortage — closed, should be excluded',
                'vessel_id' => $coralVoyager->id,
                'report_date' => '2026-05-15',
                'status' => 'CLOSED',
            ],
        ];

        foreach ($rows as $row) {
            Claim::updateOrCreate(['claim_no' => $row['claim_no']], $row);
        }
    }
}
