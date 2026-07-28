<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/External.php's add_external_report(). Not
     * ported: bookID/SIRE observation linkage (no Observations module),
     * file attachments (no file storage anywhere in this app).
     *
     * Renames vs. legacy: master -> master_name, auditor -> auditor_name
     * (a real Address Book FK in legacy, scoped to the "CLASSIFICATION
     * SOCIETY" category — since Address Book isn't migrated, this lands
     * as free text, same convention as Company Inspection's inspector
     * and Internal Audit's auditor).
     *
     * vessel_remarks is kept even though this admin's add/edit form
     * always renders it `disabled` (read-only) — legacy only lets the
     * unmigrated vessel-side app populate it. It's stored for display
     * only; the API/frontend never accepts it as writable input.
     *
     * typeof_audit is the same fixed 4-option set as Company/Internal
     * Audits (ISM / ISPS / MLC / ISM/ISPS/MLC), not a lookup table.
     *
     * The dashboard-phase `ref_no` unique() is dropped for the same
     * reason as every other report module: legacy's duplicate check is
     * scoped to non-deleted rows, so a ref from a deleted report is
     * reusable. That scoped rule lives in ExternalAuditRequest instead.
     */
    public function up(): void
    {
        Schema::table('external_audit_reports', function (Blueprint $table) {
            $table->dropUnique(['ref_no']);

            $table->string('department')->nullable()->after('vessel_id');
            $table->string('portof_audit')->nullable()->after('dateof_audit');
            $table->string('typeof_audit')->nullable()->after('portof_audit');
            $table->string('master_name')->nullable()->after('typeof_audit');
            $table->string('chief_engineer')->nullable()->after('master_name');
            $table->string('auditor_name')->nullable()->after('chief_engineer');
            $table->text('shore_remarks')->nullable()->after('auditor_name');
            $table->text('vessel_remarks')->nullable()->after('shore_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('external_audit_reports', function (Blueprint $table) {
            $table->dropColumn([
                'department', 'portof_audit', 'typeof_audit', 'master_name',
                'chief_engineer', 'auditor_name', 'shore_remarks', 'vessel_remarks',
            ]);
            $table->unique('ref_no');
        });
    }
};
