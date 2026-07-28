<?php

namespace Database\Seeders;

use App\Models\PscMouAuthority;
use App\Models\PscReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Fills in the full-record fields on the PscReport rows already created
 * by AuditReportExtraSeeder (PSC-2026-001/002/003, which have linked
 * Nonconformity rows keyed on their ref_no — updateOrCreate keeps those
 * links intact), plus adds a couple more rows to exercise the
 * detained-without-released branch and the MOU "Others" branch.
 */
class PscReportSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PscMouAuthoritySeeder::class);

        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        $paris = PscMouAuthority::where('name', 'Paris MOU')->value('id');
        $tokyo = PscMouAuthority::where('name', 'Tokyo MOU')->value('id');
        $uscg = PscMouAuthority::where('name', 'USCG')->value('id');
        $others = PscMouAuthority::where('name', 'Others')->value('id');

        // Detained and subsequently released.
        PscReport::updateOrCreate(['ref_no' => 'PSC-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-04',
            'placeof_inspection' => 'Rotterdam, Netherlands',
            'mou_id' => $paris,
            'name_psco' => 'J. van der Berg',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'is_detained' => true,
            'detained_date' => '2026-07-04',
            'detained_time' => '09:15',
            'is_released' => true,
            'released_date' => '2026-07-06',
            'released_time' => '14:00',
            'closing_date' => null,
            'remarks' => 'Detained pending rectification of steering gear deficiency; released after satisfactory re-inspection.',
            'is_deleted' => false,
        ]);

        // Closed report — exercises the Re-open action.
        PscReport::updateOrCreate(['ref_no' => 'PSC-2026-002'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-06-12',
            'placeof_inspection' => 'Singapore',
            'mou_id' => $tokyo,
            'name_psco' => 'S. Lim',
            'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes',
            'is_detained' => false,
            'closing_date' => '2026-06-20',
            'remarks' => 'Minor deficiencies, all closed out.',
            'is_deleted' => false,
        ]);

        // No linked NCs, MOU "Others" branch.
        PscReport::updateOrCreate(['ref_no' => 'PSC-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-16',
            'placeof_inspection' => 'Fujairah, UAE',
            'mou_id' => $others,
            'mou_others' => 'Fujairah Port Authority',
            'name_psco' => 'A. Hassan',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'is_detained' => false,
            'closing_date' => null,
            'is_deleted' => false,
        ]);

        // Detained, not yet released.
        PscReport::updateOrCreate(['ref_no' => 'PSC-2026-004'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-07-20',
            'placeof_inspection' => 'Houston, USA',
            'mou_id' => $uscg,
            'name_psco' => 'M. Carter',
            'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes',
            'is_detained' => true,
            'detained_date' => '2026-07-20',
            'detained_time' => '11:30',
            'is_released' => false,
            'closing_date' => null,
            'remarks' => 'Detained for ISM-related deficiencies; awaiting corrective action verification.',
            'is_deleted' => false,
        ]);

        // Plain, no detention.
        PscReport::updateOrCreate(['ref_no' => 'PSC-2026-005'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-05-05',
            'placeof_inspection' => 'Antwerp, Belgium',
            'mou_id' => $paris,
            'name_psco' => 'L. Dubois',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'is_detained' => false,
            'closing_date' => '2026-05-10',
            'is_deleted' => false,
        ]);
    }
}
