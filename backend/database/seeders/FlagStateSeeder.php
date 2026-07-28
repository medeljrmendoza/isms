<?php

namespace Database\Seeders;

use App\Models\FlagState\FlagStateReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Fills in the full-record fields on the FlagStateReport rows already
 * created by NonSireFlagStateSeeder (FLAG-2026-001..005, which cover
 * every added_by/is_published/is_approved combination the dashboard
 * dashlet's filter branches — updateOrCreate keeps that intact while
 * adding the rest of the record). Safe to key on ref_no (a string, not a
 * date-cast column), same as ExternalAuditSeeder.
 */
class FlagStateSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // SHORE, published, approved — fully closed out. Has a pending NC (NC-4008) so still shows via that.
        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-01',
            'placeof_inspection' => 'Port of Piraeus',
            'inspector' => 'M. Konstantinou',
            'flag_cost' => 1750.00,
            'shore_remarks' => 'Annual flag state inspection; one deficiency still open.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => true,
            'is_deleted' => false,
        ]);

        // SHORE, published, unapproved — needs approval regardless of NCs.
        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-002'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-07-06',
            'placeof_inspection' => 'Port of Valletta',
            'inspector' => 'J. Borg',
            'flag_cost' => 1600.00,
            'shore_remarks' => 'Flag state inspection; awaiting DPA approval.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // VESSEL-added, unapproved — needs approval regardless of publish state (VESSEL rows have none).
        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-10',
            'placeof_inspection' => 'Port of Limassol',
            'inspector' => 'A. Georgiou',
            'vessel_remarks' => 'Flag state inspection conducted at anchorage; report submitted by vessel.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // SHORE, unpublished — excluded (needs publishing before it can need approval).
        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-004'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-05-18',
            'placeof_inspection' => 'Port of Constanta',
            'inspector' => 'D. Ionescu',
            'flag_cost' => 1450.00,
            'shore_remarks' => 'Draft, not yet published for approval.',
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // VESSEL-added, already approved — excluded, zero NCs.
        FlagStateReport::updateOrCreate(['ref_no' => 'FLAG-2026-005'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-05-22',
            'placeof_inspection' => 'Port of Bar',
            'inspector' => 'N. Vukovic',
            'vessel_remarks' => 'Flag state inspection; no findings raised.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
    }
}
