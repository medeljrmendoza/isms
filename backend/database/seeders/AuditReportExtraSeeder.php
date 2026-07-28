<?php

namespace Database\Seeders;

use App\Models\ExternalAudits\ExternalAuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\PscReports\PscReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

class AuditReportExtraSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // --- PSC Inspections ---
        $pscOpen = PscReport::updateOrCreate(['ref_no' => 'PSC-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-04',
            'is_deleted' => false,
        ]);
        $pscClosed = PscReport::updateOrCreate(['ref_no' => 'PSC-2026-002'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_inspection' => '2026-06-12',
            'is_deleted' => false,
        ]);
        PscReport::updateOrCreate(['ref_no' => 'PSC-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'dateof_inspection' => '2026-07-16',
            'is_deleted' => false,
        ]);

        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4005'], [
            'date_of_nc' => '2026-07-04',
            'vessel_id' => $pacificStar->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Deficiency noted during PSC inspection',
            'added_by' => 'SHORE',
            'source_of_nc' => 'PSC INSPECTION',
            'source_of_nc_ref_no' => $pscOpen->ref_no,
            'is_published' => true,
            'is_approved' => false,
            'is_inactive' => false,
            'close_out_date' => null,
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4006'], [
            'date_of_nc' => '2026-06-12',
            'vessel_id' => $coralVoyager->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Closed already — should not count as pending',
            'added_by' => 'SHORE',
            'source_of_nc' => 'PSC INSPECTION',
            'source_of_nc_ref_no' => $pscClosed->ref_no,
            'is_published' => true,
            'is_approved' => true,
            'is_inactive' => false,
            'close_out_date' => '2026-06-18',
        ]);
        // PSC-2026-003 intentionally has no linked non-conformities at all.

        // --- External Audits ---
        // Shows via pending NC, despite being an already-approved report.
        $extApproved = ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'dateof_audit' => '2026-07-02',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4007'], [
            'date_of_nc' => '2026-07-02',
            'vessel_id' => $pacificStar->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Finding from external audit still open',
            'added_by' => 'SHORE',
            'source_of_nc' => 'EXTERNAL AUDIT',
            'source_of_nc_ref_no' => $extApproved->ref_no,
            'is_published' => true,
            'is_approved' => false,
            'is_inactive' => false,
            'close_out_date' => null,
        ]);

        // Shows via the SHORE + published + unapproved trigger, zero NCs.
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-002'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_audit' => '2026-07-08',
            'added_by' => 'SHORE',
            'is_published' => true,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // Shows via the VESSEL + unapproved trigger, zero NCs.
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'dateof_audit' => '2026-07-12',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // Excluded: SHORE but unpublished, so the approval trigger doesn't apply, and zero NCs.
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-004'], [
            'vessel_id' => $coralVoyager->id,
            'dateof_audit' => '2026-05-20',
            'added_by' => 'SHORE',
            'is_published' => false,
            'is_approved' => false,
            'is_deleted' => false,
        ]);

        // Excluded: already approved and zero NCs.
        ExternalAuditReport::updateOrCreate(['ref_no' => 'EXT-2026-005'], [
            'vessel_id' => $pacificStar->id,
            'dateof_audit' => '2026-05-25',
            'added_by' => 'VESSEL',
            'is_published' => false,
            'is_approved' => true,
            'is_deleted' => false,
        ]);
    }
}
