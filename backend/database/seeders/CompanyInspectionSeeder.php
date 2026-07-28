<?php

namespace Database\Seeders;

use App\Models\CompanyInspections\AuditKind;
use App\Models\CompanyInspections\AuditReport;
use App\Models\CompanyInspections\AuditType;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Fills in the full-record fields on the AuditReport rows already
 * created by AuditReportSeeder (CO-2026-001/002/003, which have linked
 * Nonconformity rows keyed on their audit_ref — updateOrCreate keeps
 * those links intact), plus adds two more to widen the vessel/company
 * mix on the module list.
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

        $scheduled = AuditKind::where('name', 'Scheduled')->value('id');
        $unscheduled = AuditKind::where('name', 'Unscheduled')->value('id');
        $followUp = AuditKind::where('name', 'Follow-up')->value('id');
        $annual = AuditKind::where('name', 'Annual')->value('id');

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
    }
}
