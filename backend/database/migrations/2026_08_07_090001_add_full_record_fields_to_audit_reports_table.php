<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/Company.php's add_company_report(). Not
     * ported: bookID/SIRE observation linkage (no Observations module),
     * file attachments (no file storage anywhere in this app), and
     * date_closed — the closing-date input is commented out in
     * add_company_report.php, the list has no Re-open button, and
     * reopen_company_report() is orphaned dead code, so the field has
     * no reachable UI in legacy. (The dashboard-phase migration already
     * dropped it for the same reason.)
     *
     * Renames vs. legacy column names, following the convention set by
     * psc_reports: audit_master -> master_name, audit_chief_eng ->
     * chief_engineer. audit_auditor is a real FK into tb_address_book in
     * legacy, but the Address Book module isn't migrated, so it lands
     * here as free text (inspector_name) rather than a dangling FK.
     *
     * The dashboard-phase `audit_ref` unique() is dropped for the same
     * reason as psc_reports.ref_no: legacy's duplicate check is scoped
     * to non-deleted rows, so a ref from a deleted report is reusable.
     * That scoped rule lives in CompanyInspectionRequest instead.
     */
    public function up(): void
    {
        Schema::table('audit_reports', function (Blueprint $table) {
            $table->dropUnique(['audit_ref']);

            $table->string('department')->nullable()->after('vessel_company');
            $table->string('placeof_audit')->nullable()->after('this_date');
            $table->foreignId('audit_type_id')->nullable()->after('placeof_audit')->constrained('audit_types')->nullOnDelete();
            $table->foreignId('audit_kind_id')->nullable()->after('audit_type_id')->constrained('audit_kinds')->nullOnDelete();
            $table->string('inspector_name')->nullable()->after('audit_kind_id');
            $table->string('master_name')->nullable()->after('inspector_name');
            $table->string('chief_engineer')->nullable()->after('master_name');
            $table->text('remarks')->nullable()->after('chief_engineer');
        });
    }

    public function down(): void
    {
        Schema::table('audit_reports', function (Blueprint $table) {
            $table->dropForeign(['audit_type_id']);
            $table->dropForeign(['audit_kind_id']);
            $table->dropColumn([
                'department', 'placeof_audit', 'audit_type_id', 'audit_kind_id',
                'inspector_name', 'master_name', 'chief_engineer', 'remarks',
            ]);
            $table->unique('audit_ref');
        });
    }
};
