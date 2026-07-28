<?php

namespace Database\Seeders;

use App\Models\ExternalAudits\ExternalAuditReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Fills in the full-record fields on the ExternalAuditReport rows
 * already created by AuditReportExtraSeeder (EXT-2026-001..005, which
 * cover every added_by/is_published/is_approved combination the
 * dashboard dashlet's filter branches — updateOrCreate keeps that intact
 * while adding the rest of the record).
 */
class ExternalAuditSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // SHORE, published, approved — fully closed out. Shows via pending NC only.
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'department' => 'Deck',
            'dateof_audit' => '2026-07-02',
            'portof_audit' => 'Rotterdam, Netherlands',
            'typeof_audit' => 'ISM',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'auditor_name' => 'Lloyd\'s Register',
            'shore_remarks' => 'Annual ISM external audit; one finding still open.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => true,
            'is_deleted' => false,
        ]);

        // SHORE, published, unapproved — needs approval regardless of NCs.
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-002'], [
            'vessel_id' => $coralVoyager->id,
            'department' => 'Engine',
            'dateof_audit' => '2026-07-08',
            'portof_audit' => 'Singapore',
            'typeof_audit' => 'ISPS',
            'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes',
            'auditor_name' => 'DNV',
            'shore_remarks' => 'ISPS external audit; awaiting DPA approval.',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // VESSEL-added, unapproved — needs approval regardless of publish state (VESSEL rows have none).
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'department' => 'Deck',
            'dateof_audit' => '2026-07-12',
            'portof_audit' => 'Busan, South Korea',
            'typeof_audit' => 'MLC',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'auditor_name' => 'ClassNK',
            'vessel_remarks' => 'MLC external audit conducted at anchorage; report submitted by vessel.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // SHORE, unpublished — excluded (needs publishing before it can need approval).
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-004'], [
            'vessel_id' => $coralVoyager->id,
            'department' => 'Deck',
            'dateof_audit' => '2026-05-20',
            'portof_audit' => 'Fujairah, UAE',
            'typeof_audit' => 'ISM/ISPS/MLC',
            'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes',
            'auditor_name' => 'Bureau Veritas',
            'shore_remarks' => 'Combined external audit draft, not yet published for approval.',
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // VESSEL-added, already approved — excluded, zero NCs.
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-005'], [
            'vessel_id' => $pacificStar->id,
            'department' => 'Engine',
            'dateof_audit' => '2026-05-25',
            'portof_audit' => 'Antwerp, Belgium',
            'typeof_audit' => 'ISM',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'auditor_name' => 'Lloyd\'s Register',
            'vessel_remarks' => 'ISM external audit; no findings raised.',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
    }
}
