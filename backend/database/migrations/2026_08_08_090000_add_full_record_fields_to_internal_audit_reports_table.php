<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/Internal.php's add_internal_report(). Not
     * ported: bookID/SIRE observation linkage (no Observations module),
     * file attachments (no file storage anywhere in this app), and
     * date_closed — same as company_inspections/audit_reports, the
     * closing-date input is commented out in add_internal_report.php,
     * the list has no Re-open button, and reopen_internal_report() is
     * unreachable dead code.
     *
     * Renames vs. legacy: audit_master -> master_name, audit_chief_eng
     * -> chief_engineer, audit_auditor -> auditor_name (free text in
     * legacy too — unlike Company Inspection's inspector, this was never
     * an Address Book FK).
     *
     * typeof_audit is a fixed 4-option HTML <select> in legacy
     * (ISM / ISPS / MLC / ISM/ISPS/MLC), not a lookup table — kept as a
     * plain string column, validated against that set at the
     * FormRequest layer (same convention as vessel_company, bac, etc.
     * elsewhere).
     *
     * The dashboard-phase `audit_ref` unique() is dropped for the same
     * reason as every other report module: legacy's duplicate check is
     * scoped to non-deleted rows, so a ref from a deleted report is
     * reusable. That scoped rule lives in InternalAuditRequest instead.
     */
    public function up(): void
    {
        Schema::table('internal_audit_reports', function (Blueprint $table) {
            $table->dropUnique(['audit_ref']);

            $table->string('department')->nullable()->after('vessel_id');
            $table->string('placeof_audit')->nullable()->after('this_date');
            $table->string('typeof_audit')->nullable()->after('placeof_audit');
            $table->string('master_name')->nullable()->after('typeof_audit');
            $table->string('chief_engineer')->nullable()->after('master_name');
            $table->string('auditor_name')->nullable()->after('chief_engineer');
            $table->text('remarks')->nullable()->after('auditor_name');
        });
    }

    public function down(): void
    {
        Schema::table('internal_audit_reports', function (Blueprint $table) {
            $table->dropColumn([
                'department', 'placeof_audit', 'typeof_audit',
                'master_name', 'chief_engineer', 'auditor_name', 'remarks',
            ]);
            $table->unique('audit_ref');
        });
    }
};
