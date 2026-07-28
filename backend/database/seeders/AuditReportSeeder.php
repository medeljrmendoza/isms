<?php

namespace Database\Seeders;

use App\Models\CompanyInspections\AuditReport;
use App\Models\InternalAudits\InternalAuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Seeds audit reports plus the non-conformities that link back to them
 * via source_of_nc_ref_no, covering: a report with an open (pending) NC
 * (should appear), a report whose only NC is closed (should be
 * excluded), and a report with no linked NCs at all (should be
 * excluded).
 */
class AuditReportSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);

        // --- Company Inspections ---
        $companyOpen = AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'vessel_company' => 'VESSEL',
            'this_date' => '2026-07-05',
            'is_deleted' => false,
        ]);
        $companyClosed = AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-002'], [
            'company' => 'BTSolve Shipping',
            'vessel_company' => 'COMPANY',
            'this_date' => '2026-06-10',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-003'], [
            'vessel_id' => $coralVoyager->id,
            'vessel_company' => 'VESSEL',
            'this_date' => '2026-07-15',
            'is_deleted' => false,
        ]);

        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4001'], [
            'date_of_nc' => '2026-07-06',
            'vessel_id' => $pacificStar->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Fire drill records incomplete for Q2',
            'added_by' => 'SHORE',
            'source_of_nc' => 'COMPANY INSPECTION',
            'source_of_nc_ref_no' => $companyOpen->audit_ref,
            'is_published' => true,
            'is_approved' => false,
            'is_inactive' => false,
            'close_out_date' => null,
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4002'], [
            'date_of_nc' => '2026-06-11',
            'company' => 'BTSolve Shipping',
            'vessel_company' => 'COMPANY',
            'description' => 'Closed already — should not count as pending',
            'added_by' => 'SHORE',
            'source_of_nc' => 'COMPANY INSPECTION',
            'source_of_nc_ref_no' => $companyClosed->audit_ref,
            'is_published' => true,
            'is_approved' => true,
            'is_inactive' => false,
            'close_out_date' => '2026-06-20',
        ]);
        // CO-2026-003 intentionally has no linked non-conformities at all.

        // --- Internal Audits ---
        $internalOpen = InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-001'], [
            'vessel_id' => $coralVoyager->id,
            'this_date' => '2026-07-09',
            'is_deleted' => false,
        ]);
        $internalClosed = InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-002'], [
            'vessel_id' => $pacificStar->id,
            'this_date' => '2026-06-01',
            'is_deleted' => false,
        ]);
        InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'this_date' => '2026-07-18',
            'is_deleted' => false,
        ]);

        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4003'], [
            'date_of_nc' => '2026-07-10',
            'vessel_id' => $coralVoyager->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Engine room housekeeping deficiency',
            'added_by' => 'SHORE',
            'source_of_nc' => 'INTERNAL AUDIT',
            'source_of_nc_ref_no' => $internalOpen->audit_ref,
            'is_published' => true,
            'is_approved' => false,
            'is_inactive' => false,
            'close_out_date' => null,
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4004'], [
            'date_of_nc' => '2026-06-02',
            'vessel_id' => $pacificStar->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Closed already — should not count as pending',
            'added_by' => 'SHORE',
            'source_of_nc' => 'INTERNAL AUDIT',
            'source_of_nc_ref_no' => $internalClosed->audit_ref,
            'is_published' => true,
            'is_approved' => true,
            'is_inactive' => false,
            'close_out_date' => '2026-06-05',
        ]);
        // IA-2026-003 intentionally has no linked non-conformities at all.
    }
}
