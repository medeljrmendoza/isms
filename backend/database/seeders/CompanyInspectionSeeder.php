<?php

namespace Database\Seeders;

use App\Models\CompanyInspections\AuditKind;
use App\Models\CompanyInspections\AuditReport;
use App\Models\CompanyInspections\AuditType;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Fills in the full-record fields on the AuditReport rows already
 * created by AuditReportSeeder (CO-2026-001/002/003, which have linked
 * Nonconformity rows keyed on their audit_ref — updateOrCreate keeps
 * those links intact), plus adds two more to widen the vessel/company
 * mix on the module list. CO-2026-006 onward is a wider KPI demo batch
 * (spread across vessels/companies/months) so the KPI - Company
 * Inspections charts show real variation instead of a flat count of 1
 * per bar.
 */
class CompanyInspectionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AuditTypeKindSeeder::class);

        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);
        $northernLight = Vessel::firstOrCreate(['name' => 'Northern Light'], ['prefix' => 'MV']);

        $safety = AuditType::where('name', 'Safety Inspection')->value('id');
        $technical = AuditType::where('name', 'Technical Inspection')->value('id');
        $marine = AuditType::where('name', 'Marine Superintendent Inspection')->value('id');
        $navigation = AuditType::where('name', 'Navigation Audit')->value('id');

        $ism = AuditType::where('name', 'ISM Internal Audit')->value('id');

        $scheduled = AuditKind::where('name', 'Scheduled')->value('id');
        $unscheduled = AuditKind::where('name', 'Unscheduled')->value('id');
        $followUp = AuditKind::where('name', 'Follow-up')->value('id');
        $annual = AuditKind::where('name', 'Annual')->value('id');
        $intermediate = AuditKind::where('name', 'Intermediate')->value('id');
        $special = AuditKind::where('name', 'Special')->value('id');

        // Vessel-attributed, has an open NC (drives the dashboard dashlet).
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-001'], [
            'vessel_id' => $pacificStar->id,
            'company' => null,
            'vessel_company' => 'VESSEL',
            'department' => 'Deck',
            'this_date' => '2026-07-05',
            'placeof_audit' => 'Singapore',
            'audit_type_id' => $safety,
            'audit_kind_id' => $scheduled,
            'inspector_name' => 'BTSolve Shipping (M. Santos)',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'remarks' => 'Routine quarterly safety inspection; fire drill records found incomplete.',
            'is_deleted' => false,
        ]);

        // Company-attributed — exercises the COMPANY branch of the toggle.
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-002'], [
            'vessel_id' => null,
            'company' => 'BTSolve Shipping',
            'vessel_company' => 'COMPANY',
            'department' => 'Operations',
            'this_date' => '2026-06-10',
            'placeof_audit' => 'Head Office, Manila',
            'audit_type_id' => $marine,
            'audit_kind_id' => $annual,
            'inspector_name' => 'BTSolve Shipping (A. Villanueva)',
            'remarks' => 'Annual office-side review of operational procedures.',
            'is_deleted' => false,
        ]);

        // Vessel-attributed, no linked NCs.
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-003'], [
            'vessel_id' => $coralVoyager->id,
            'company' => null,
            'vessel_company' => 'VESSEL',
            'department' => 'Engine',
            'this_date' => '2026-07-15',
            'placeof_audit' => 'Rotterdam, Netherlands',
            'audit_type_id' => $technical,
            'audit_kind_id' => $unscheduled,
            'inspector_name' => 'BTSolve Shipping (R. Cruz)',
            'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes',
            'remarks' => 'Unscheduled technical inspection during port call; no findings raised.',
            'is_deleted' => false,
        ]);

        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-004'], [
            'vessel_id' => $northernLight->id,
            'company' => null,
            'vessel_company' => 'VESSEL',
            'department' => 'Deck',
            'this_date' => '2026-07-22',
            'placeof_audit' => 'Busan, South Korea',
            'audit_type_id' => $navigation,
            'audit_kind_id' => $followUp,
            'inspector_name' => 'BTSolve Shipping (M. Santos)',
            'master_name' => 'Capt. L. Fernandez',
            'chief_engineer' => 'C/E T. Aquino',
            'remarks' => 'Follow-up navigation audit verifying bridge procedure corrections.',
            'is_deleted' => false,
        ]);

        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-005'], [
            'vessel_id' => null,
            'company' => 'BTSolve Crewing Services',
            'vessel_company' => 'COMPANY',
            'department' => 'Crewing',
            'this_date' => '2026-05-18',
            'placeof_audit' => 'Branch Office, Cebu',
            'audit_type_id' => $safety,
            'audit_kind_id' => $scheduled,
            'inspector_name' => 'BTSolve Shipping (A. Villanueva)',
            'remarks' => 'Crewing office inspection covering training records and certification tracking.',
            'is_deleted' => false,
        ]);

        // --- Wider KPI demo batch: gives every vessel and company a
        // distinct, non-uniform report count, and spreads dates across
        // several months so the KPI date-range filter has something to
        // visibly narrow. ---

        // Pacific Star: 4 more reports (total 5 with CO-2026-001).
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-006'], [
            'vessel_id' => $pacificStar->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'Engine', 'this_date' => '2026-07-25', 'placeof_audit' => 'Fujairah, UAE',
            'audit_type_id' => $technical, 'audit_kind_id' => $unscheduled,
            'inspector_name' => 'BTSolve Shipping (R. Cruz)', 'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo', 'remarks' => 'Unscheduled engine room technical inspection.',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-007'], [
            'vessel_id' => $pacificStar->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'Bridge', 'this_date' => '2026-07-19', 'placeof_audit' => 'Colombo, Sri Lanka',
            'audit_type_id' => $marine, 'audit_kind_id' => $special,
            'inspector_name' => 'BTSolve Shipping (A. Villanueva)', 'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo', 'remarks' => 'Special marine superintendent visit following near-miss report.',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-008'], [
            'vessel_id' => $pacificStar->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'Deck', 'this_date' => '2026-05-14', 'placeof_audit' => 'Jebel Ali, UAE',
            'audit_type_id' => $safety, 'audit_kind_id' => $intermediate,
            'inspector_name' => 'BTSolve Shipping (M. Santos)', 'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo', 'remarks' => 'Intermediate safety inspection ahead of vetting season.',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-009'], [
            'vessel_id' => $pacificStar->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'ISM', 'this_date' => '2026-06-02', 'placeof_audit' => 'Piraeus, Greece',
            'audit_type_id' => $ism, 'audit_kind_id' => $annual,
            'inspector_name' => 'BTSolve Shipping (R. Cruz)', 'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo', 'remarks' => 'Annual ISM internal audit — no major nonconformities.',
            'is_deleted' => false,
        ]);

        // Coral Voyager: 1 more report (total 2 with CO-2026-003).
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-010'], [
            'vessel_id' => $coralVoyager->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'Deck', 'this_date' => '2026-07-20', 'placeof_audit' => 'Santos, Brazil',
            'audit_type_id' => $safety, 'audit_kind_id' => $scheduled,
            'inspector_name' => 'BTSolve Shipping (M. Santos)', 'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes', 'remarks' => 'Routine scheduled safety inspection during cargo ops.',
            'is_deleted' => false,
        ]);

        // Northern Light: 3 more reports (total 4 with CO-2026-004).
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-011'], [
            'vessel_id' => $northernLight->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'Engine', 'this_date' => '2026-07-08', 'placeof_audit' => 'Busan, South Korea',
            'audit_type_id' => $technical, 'audit_kind_id' => $scheduled,
            'inspector_name' => 'BTSolve Shipping (R. Cruz)', 'master_name' => 'Capt. L. Fernandez',
            'chief_engineer' => 'C/E T. Aquino', 'remarks' => 'Scheduled technical inspection; minor deficiency noted.',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-012'], [
            'vessel_id' => $northernLight->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'Bridge', 'this_date' => '2026-06-15', 'placeof_audit' => 'Yokohama, Japan',
            'audit_type_id' => $marine, 'audit_kind_id' => $unscheduled,
            'inspector_name' => 'BTSolve Shipping (A. Villanueva)', 'master_name' => 'Capt. L. Fernandez',
            'chief_engineer' => 'C/E T. Aquino', 'remarks' => 'Unscheduled marine superintendent visit.',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-013'], [
            'vessel_id' => $northernLight->id, 'company' => null, 'vessel_company' => 'VESSEL',
            'department' => 'Deck', 'this_date' => '2026-05-25', 'placeof_audit' => 'Vancouver, Canada',
            'audit_type_id' => $safety, 'audit_kind_id' => $followUp,
            'inspector_name' => 'BTSolve Shipping (M. Santos)', 'master_name' => 'Capt. L. Fernandez',
            'chief_engineer' => 'C/E T. Aquino', 'remarks' => 'Follow-up safety inspection verifying prior corrective actions.',
            'is_deleted' => false,
        ]);

        // BTSolve Shipping (company): 1 more report (total 2 with CO-2026-002).
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-014'], [
            'vessel_id' => null, 'company' => 'BTSolve Shipping', 'vessel_company' => 'COMPANY',
            'department' => 'Technical', 'this_date' => '2026-07-18', 'placeof_audit' => 'Head Office, Manila',
            'audit_type_id' => $technical, 'audit_kind_id' => $intermediate,
            'inspector_name' => 'BTSolve Shipping (R. Cruz)',
            'remarks' => 'Intermediate technical department review.',
            'is_deleted' => false,
        ]);

        // BTSolve Marine Services (new company): 3 reports.
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-015'], [
            'vessel_id' => null, 'company' => 'BTSolve Marine Services', 'vessel_company' => 'COMPANY',
            'department' => 'Marine', 'this_date' => '2026-06-08', 'placeof_audit' => 'Branch Office, Batangas',
            'audit_type_id' => $safety, 'audit_kind_id' => $scheduled,
            'inspector_name' => 'BTSolve Shipping (A. Villanueva)',
            'remarks' => 'Scheduled marine office safety inspection.',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-016'], [
            'vessel_id' => null, 'company' => 'BTSolve Marine Services', 'vessel_company' => 'COMPANY',
            'department' => 'Marine', 'this_date' => '2026-07-11', 'placeof_audit' => 'Branch Office, Batangas',
            'audit_type_id' => $marine, 'audit_kind_id' => $unscheduled,
            'inspector_name' => 'BTSolve Shipping (M. Santos)',
            'remarks' => 'Unscheduled marine superintendent office review.',
            'is_deleted' => false,
        ]);
        AuditReport::updateOrCreate(['audit_ref' => 'CO-2026-017'], [
            'vessel_id' => null, 'company' => 'BTSolve Marine Services', 'vessel_company' => 'COMPANY',
            'department' => 'Technical', 'this_date' => '2026-05-20', 'placeof_audit' => 'Branch Office, Batangas',
            'audit_type_id' => $technical, 'audit_kind_id' => $annual,
            'inspector_name' => 'BTSolve Shipping (R. Cruz)',
            'remarks' => 'Annual technical department office review.',
            'is_deleted' => false,
        ]);

        // Linked non-conformities so "Non Conformities per Vessel/Company"
        // also shows real variation (Pacific Star=2, Northern Light=1,
        // BTSolve Shipping=2, BTSolve Marine Services=1).
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4009'], [
            'date_of_nc' => '2026-07-25',
            'vessel_id' => $pacificStar->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Fuel oil purifier deficiency noted during technical inspection',
            'added_by' => 'SHORE',
            'source_of_nc' => 'COMPANY INSPECTION',
            'source_of_nc_ref_no' => 'CO-2026-006',
            'is_published' => true,
            'is_approved' => false,
            'is_inactive' => false,
            'close_out_date' => null,
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4010'], [
            'date_of_nc' => '2026-07-08',
            'vessel_id' => $northernLight->id,
            'vessel_company' => 'VESSEL',
            'description' => 'Minor deficiency in engine room lighting',
            'added_by' => 'SHORE',
            'source_of_nc' => 'COMPANY INSPECTION',
            'source_of_nc_ref_no' => 'CO-2026-011',
            'is_published' => true,
            'is_approved' => true,
            'is_inactive' => false,
            'close_out_date' => '2026-07-14',
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4011'], [
            'date_of_nc' => '2026-07-18',
            'vessel_id' => null,
            'company' => 'BTSolve Shipping',
            'vessel_company' => 'COMPANY',
            'description' => 'Technical department procedure gap identified during office review',
            'added_by' => 'SHORE',
            'source_of_nc' => 'COMPANY INSPECTION',
            'source_of_nc_ref_no' => 'CO-2026-014',
            'is_published' => true,
            'is_approved' => false,
            'is_inactive' => false,
            'close_out_date' => null,
        ]);
        Nonconformity::updateOrCreate(['ncr_no' => 'NC-4012'], [
            'date_of_nc' => '2026-06-08',
            'vessel_id' => null,
            'company' => 'BTSolve Marine Services',
            'vessel_company' => 'COMPANY',
            'description' => 'Marine office safety records incomplete',
            'added_by' => 'SHORE',
            'source_of_nc' => 'COMPANY INSPECTION',
            'source_of_nc_ref_no' => 'CO-2026-015',
            'is_published' => true,
            'is_approved' => true,
            'is_inactive' => false,
            'close_out_date' => '2026-06-20',
        ]);
    }
}
