<?php

namespace Database\Seeders;

use App\Models\CompanyInspections\AuditKind;
use App\Models\CompanyInspections\AuditType;
use Illuminate\Database\Seeder;

/**
 * Stands in for legacy's pl_audit_types / pl_audit_kinds. The legacy
 * dump for these lookups isn't in the ported folders (they're loaded by
 * BaseController helpers that live outside Controllers/), so these are
 * representative maritime values rather than a verbatim copy.
 */
class AuditTypeKindSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'Safety Inspection',
            'Technical Inspection',
            'Marine Superintendent Inspection',
            'Navigation Audit',
            'ISM Internal Audit',
            'Pre-Vetting Inspection',
            'Cargo Operations Inspection',
        ];

        $kinds = [
            'Scheduled',
            'Unscheduled',
            'Follow-up',
            'Annual',
            'Intermediate',
            'Special',
        ];

        foreach ($types as $name) {
            AuditType::firstOrCreate(['name' => $name]);
        }

        foreach ($kinds as $name) {
            AuditKind::firstOrCreate(['name' => $name]);
        }
    }
}
