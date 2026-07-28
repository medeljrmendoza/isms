<?php

namespace Database\Seeders;

use App\Models\InternalAudits\InternalAuditReport;
use App\Models\Vessel;
use Illuminate\Database\Seeder;

/**
 * Fills in the full-record fields on the InternalAuditReport rows
 * already created by AuditReportSeeder (IA-2026-001/002/003, which have
 * linked Nonconformity rows keyed on their audit_ref — updateOrCreate
 * keeps those links intact), plus adds two more to widen the
 * typeof_audit enum coverage.
 */
class InternalAuditSeeder extends Seeder
{
    public function run(): void
    {
        $pacificStar = Vessel::firstOrCreate(['name' => 'Pacific Star'], ['prefix' => 'MV']);
        $coralVoyager = Vessel::firstOrCreate(['name' => 'Coral Voyager'], ['prefix' => 'MV']);
        $northernLight = Vessel::firstOrCreate(['name' => 'Northern Light'], ['prefix' => 'MV']);

        // Has an open NC (drives the dashboard dashlet).
        InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-001'], [
            'vessel_id' => $coralVoyager->id,
            'department' => 'Engine',
            'this_date' => '2026-07-09',
            'placeof_audit' => 'Alongside, Busan',
            'typeof_audit' => 'ISM',
            'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes',
            'auditor_name' => 'M. Santos',
            'remarks' => 'Annual ISM internal audit; engine room housekeeping deficiency noted.',
            'is_deleted' => false,
        ]);

        // Only linked NC already closed — excluded from the dashlet.
        InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-002'], [
            'vessel_id' => $pacificStar->id,
            'department' => 'Deck',
            'this_date' => '2026-06-01',
            'placeof_audit' => 'Singapore Anchorage',
            'typeof_audit' => 'ISPS',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'auditor_name' => 'A. Villanueva',
            'remarks' => 'ISPS internal audit; finding closed out same month.',
            'is_deleted' => false,
        ]);

        // No linked NCs at all.
        InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-003'], [
            'vessel_id' => $pacificStar->id,
            'department' => 'Deck',
            'this_date' => '2026-07-18',
            'placeof_audit' => 'Rotterdam, Netherlands',
            'typeof_audit' => 'MLC',
            'master_name' => 'Capt. R. Alonzo',
            'chief_engineer' => 'C/E J. Domingo',
            'auditor_name' => 'M. Santos',
            'remarks' => 'MLC internal audit; no findings raised.',
            'is_deleted' => false,
        ]);

        InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-004'], [
            'vessel_id' => $northernLight->id,
            'department' => 'Deck',
            'this_date' => '2026-07-24',
            'placeof_audit' => 'Anchorage, Busan',
            'typeof_audit' => 'ISM/ISPS/MLC',
            'master_name' => 'Capt. L. Fernandez',
            'chief_engineer' => 'C/E T. Aquino',
            'auditor_name' => 'R. Cruz',
            'remarks' => 'Combined ISM/ISPS/MLC internal audit covering all three codes.',
            'is_deleted' => false,
        ]);

        InternalAuditReport::updateOrCreate(['audit_ref' => 'IA-2026-005'], [
            'vessel_id' => $coralVoyager->id,
            'department' => 'Engine',
            'this_date' => '2026-05-14',
            'placeof_audit' => 'Drydock, Batam',
            'typeof_audit' => 'ISM',
            'master_name' => 'Capt. E. Navarro',
            'chief_engineer' => 'C/E P. Reyes',
            'auditor_name' => 'A. Villanueva',
            'is_deleted' => false,
        ]);
    }
}
