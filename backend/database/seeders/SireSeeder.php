<?php

namespace Database\Seeders;

use App\Models\Sire\SireReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Supersedes RiskAssessmentSeeder's SireReport rows with the full record
 * (company/inspector/cost/pass-fail/remarks/added_by) plus VESSEL-added
 * rows to exercise SIRE's approve/delete-gating differences from External
 * Audits. Clears and recreates rather than updateOrCreate: matching on
 * dateof_inspection (a date-cast column) is unreliable across re-runs in
 * SQLite, same landmine fixed in IncidentReportSeeder.
 */
class SireSeeder extends Seeder
{
    public function run(): void
    {
        SireReport::query()->delete();

        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // SHORE, published, unapproved — appears in dashboard "pending" dashlet; approvable.
        SireReport::create([
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-07',
            'placeof_inspection' => 'Port of Rotterdam',
            'company_name' => 'Shell International',
            'inspector_name' => 'J. Whitfield',
            'sire_cost' => 1500.00,
            'pass_fail' => 'PASS',
            'shore_remarks' => 'Awaiting DPA approval.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // SHORE, published, approved — fully closed out, excluded from dashlet.
        SireReport::create([
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-06-10',
            'placeof_inspection' => 'Port of Singapore',
            'company_name' => 'BP Shipping',
            'inspector_name' => 'M. Alcaraz',
            'sire_cost' => 1800.00,
            'pass_fail' => 'PASS',
            'shore_remarks' => 'Closed out, no findings.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => true,
            'is_deleted' => false,
        ]);

        // SHORE, unpublished draft — excluded from dashlet, publishable.
        SireReport::create([
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-01',
            'placeof_inspection' => 'Port of Fujairah — unpublished draft',
            'company_name' => 'Vitol Bunkering',
            'inspector_name' => 'K. Osei',
            'sire_cost' => 1200.00,
            'pass_fail' => 'N/A',
            'shore_remarks' => 'Draft pending publish.',
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // SHORE, published, unapproved, deleted — excluded everywhere.
        SireReport::create([
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-05-15',
            'placeof_inspection' => 'Port of Santos — deleted',
            'company_name' => 'Petrobras',
            'inspector_name' => 'F. Nascimento',
            'sire_cost' => 1400.00,
            'pass_fail' => 'FAIL',
            'shore_remarks' => 'Superseded, soft-deleted.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => true,
        ]);

        // VESSEL-added, unapproved — approvable regardless of publish state (VESSEL rows have none); deletable.
        SireReport::create([
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-15',
            'placeof_inspection' => 'Port of Houston',
            'company_name' => 'Chevron Shipping',
            'inspector_name' => 'R. Delacruz',
            'sire_cost' => 2100.00,
            'pass_fail' => 'FAIL',
            'vessel_remarks' => 'Vessel-submitted SIRE report; two observations raised.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // VESSEL-added, already approved — not publishable, not approvable, still deletable.
        SireReport::create([
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-06-20',
            'placeof_inspection' => 'Port of Marseille',
            'company_name' => 'TotalEnergies',
            'inspector_name' => 'L. Bernard',
            'sire_cost' => 1650.00,
            'pass_fail' => 'PASS',
            'vessel_remarks' => 'Vessel-submitted SIRE report; no findings.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
    }
}
