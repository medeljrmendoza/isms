<?php

namespace Database\Seeders;

use App\Models\Claims\Claim;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class ClaimSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $rows = [
            // --- Should appear on the Dashboard's open-claims widget ---
            [
                'claim_no' => 'JPI-2201',
                'claims_category' => 'Cargo Damage',
                'vessel_id' => $pacificStar->id,
                'report_date' => '2026-07-02',
                'status' => 'OPEN',
                'nature_diagnosis' => 'Water ingress damaged 12 pallets of packaged goods during heavy weather transit.',
                'amount_usd' => 18500.00,
            ],
            [
                'claim_no' => 'JPI-2202',
                'claims_category' => 'Crew Injury',
                'vessel_id' => $coralVoyager->id,
                'report_date' => '2026-06-28',
                'status' => 'IN PROGRESS',
                'nature_diagnosis' => 'Deckhand sustained a fractured wrist while securing mooring lines.',
                'amount_usd' => 9250.00,
            ],
            [
                'claim_no' => 'JPI-2203',
                'claims_category' => 'Third-Party Property Damage',
                'vessel_id' => $pacificStar->id,
                'report_date' => '2026-07-10',
                'status' => 'PENDING DOCUMENTATION',
                'nature_diagnosis' => 'Contact with quay fendering during berthing damaged dock infrastructure.',
                'amount_usd' => 42000.00,
            ],

            // --- Closed on the Dashboard widget, but still counted by KPI Claims (no status filter there) ---
            [
                'claim_no' => 'JPI-2200',
                'claims_category' => 'Cargo Damage',
                'vessel_id' => $coralVoyager->id,
                'report_date' => '2026-05-15',
                'status' => 'CLOSED',
                'nature_diagnosis' => 'Shortage discovered on discharge, resolved as a documentation error.',
                'amount_usd' => 0.00,
            ],
        ];

        foreach ($rows as $row) {
            Claim::updateOrCreate(['claim_no' => $row['claim_no']], $row);
        }
    }
}
