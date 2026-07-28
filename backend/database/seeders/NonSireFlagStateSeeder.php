<?php

namespace Database\Seeders;

use App\Models\FlagState\FlagStateReport;
use App\Models\NonSire\NonSireReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class NonSireFlagStateSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // --- Non-SIRE ---
        $nonSireRows = [
            // Should appear
            [
                'vessel_id' => $pacificStar->id,
                'dateof_inspection' => '2026-07-13',
                'placeof_inspection' => 'Port of Hamburg',
                'is_published' => true,
                'is_approved' => false,
                'is_deleted' => false,
            ],
            // Should NOT appear
            [
                'vessel_id' => $coralVoyager->id,
                'dateof_inspection' => '2026-06-08',
                'placeof_inspection' => 'Port of Antwerp',
                'is_published' => true,
                'is_approved' => true,
                'is_deleted' => false,
            ],
            [
                'vessel_id' => $pacificStar->id,
                'dateof_inspection' => '2026-07-02',
                'placeof_inspection' => 'Port of Busan — unpublished draft',
                'is_published' => false,
                'is_approved' => false,
                'is_deleted' => false,
            ],
            [
                'vessel_id' => $coralVoyager->id,
                'dateof_inspection' => '2026-05-11',
                'placeof_inspection' => 'Port of Colombo — deleted',
                'is_published' => true,
                'is_approved' => false,
                'is_deleted' => true,
            ],
        ];

        foreach ($nonSireRows as $row) {
            NonSireReport::updateOrCreate(
                ['vessel_id' => $row['vessel_id'], 'dateof_inspection' => $row['dateof_inspection']],
                $row,
            );
        }

        // --- Flag State ---
        $flagPendingNc = FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-01',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4008'], [
            'date_of_nc' => '2026-07-01',
            'vessel_id' => $pacificStar->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Flag state deficiency still open',
            'added_by' => 'SHORE',
            'source_of_nc' => 'FLAG STATE',
            'source_of_nc_ref_no' => $flagPendingNc->ref_no,
            'is_published' => true,
            'is_approved' => false,
            'is_inactive' => false,
            'close_out_date' => null,
        ]);

        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-002'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-07-06',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-10',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // Should NOT appear: SHORE but unpublished, zero NCs.
        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-004'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-05-18',
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // Should NOT appear: already approved, zero NCs.
        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-005'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-05-22',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
    }
}
