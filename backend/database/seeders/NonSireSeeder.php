<?php

namespace Database\Seeders;

use App\Models\NonSire\NonSireReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Supersedes NonSireFlagStateSeeder's NonSireReport rows with the full
 * record (company/inspector/inspection type/cost/pass-fail/remarks/
 * added_by) plus VESSEL-added rows to exercise Non-SIRE's approve/delete
 * gating (delete is SHORE-only here, unlike SIRE). Clears and recreates
 * rather than updateOrCreate: matching on dateof_inspection (a date-cast
 * column) is unreliable across re-runs in SQLite, same landmine fixed in
 * IncidentReportSeeder/SireSeeder.
 */
class NonSireSeeder extends Seeder
{
    public function run(): void
    {
        NonSireReport::query()->delete();

        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // SHORE, published, unapproved — appears in dashboard "pending" dashlet; approvable.
        NonSireReport::create([
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-13',
            'placeof_inspection' => 'Port of Hamburg',
            'company_name' => 'Maersk Tankers',
            'inspector_name' => 'H. Voss',
            'inspection_type' => 'Terminal Inspection',
            'sire_cost' => 1300.00,
            'pass_fail' => 'PASS',
            'shore_remarks' => 'Awaiting DPA approval.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // SHORE, published, approved — fully closed out, excluded from dashlet.
        NonSireReport::create([
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-06-08',
            'placeof_inspection' => 'Port of Antwerp',
            'company_name' => 'Exxon Mobil',
            'inspector_name' => 'D. Peeters',
            'inspection_type' => 'Charterer Inspection',
            'sire_cost' => 1450.00,
            'pass_fail' => 'PASS',
            'shore_remarks' => 'Closed out, no findings.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => true,
            'is_deleted' => false,
        ]);

        // SHORE, unpublished draft — excluded from dashlet, publishable.
        NonSireReport::create([
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-02',
            'placeof_inspection' => 'Port of Busan — unpublished draft',
            'company_name' => 'GS Caltex',
            'inspector_name' => 'J. Kim',
            'inspection_type' => 'P&I Condition Survey',
            'sire_cost' => 1100.00,
            'pass_fail' => 'N/A',
            'shore_remarks' => 'Draft pending publish.',
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // SHORE, published, unapproved, deleted — excluded everywhere.
        NonSireReport::create([
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-05-11',
            'placeof_inspection' => 'Port of Colombo — deleted',
            'company_name' => 'Ceypetco',
            'inspector_name' => 'R. Fernando',
            'inspection_type' => 'Terminal Inspection',
            'sire_cost' => 1250.00,
            'pass_fail' => 'FAIL',
            'shore_remarks' => 'Superseded, soft-deleted.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => true,
        ]);

        // VESSEL-added, unapproved — approvable regardless of publish state; deletable is false here
        // (added_by=VESSEL), unlike SIRE where delete is unconditional.
        NonSireReport::create([
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-17',
            'placeof_inspection' => 'Port of Santos',
            'company_name' => 'Vale Shipping',
            'inspector_name' => 'C. Souza',
            'inspection_type' => 'Charterer Inspection',
            'sire_cost' => 1600.00,
            'pass_fail' => 'FAIL',
            'vessel_remarks' => 'Vessel-submitted Non-SIRE report; findings on mooring equipment.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // VESSEL-added, already approved — not publishable, not approvable, not deletable.
        NonSireReport::create([
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-06-22',
            'placeof_inspection' => 'Port of Piraeus',
            'company_name' => 'Motor Oil Hellas',
            'inspector_name' => 'N. Papadopoulos',
            'inspection_type' => 'P&I Condition Survey',
            'sire_cost' => 1400.00,
            'pass_fail' => 'PASS',
            'vessel_remarks' => 'Vessel-submitted Non-SIRE report; no findings.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
    }
}
