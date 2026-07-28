<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/Sire.php's add_sire_report(). Not
     * ported: bookID/SIRE observation linkage (no Observations module),
     * file attachments (no file storage anywhere in this app). Unlike
     * every other audit-style report module, SIRE has no ref_no/audit_ref
     * at all — legacy never links it to Nonconformities via
     * source_of_nc_ref_no, so there's no uniqueness rule and no delete
     * cascade to replicate here.
     *
     * company/inspector are both real Address Book FKs in legacy,
     * scoped to the "OIL MAJORS (SIRE)" and "SIRE INSPECTORS" categories
     * respectively — since Address Book isn't migrated, both land as
     * free text (company_name / inspector_name), same convention as
     * External Audit's auditor_name.
     *
     * vessel_remarks is kept even though this admin's add/edit form
     * always renders it `disabled` (read-only) — only the unmigrated
     * vessel-side app can populate it.
     */
    public function up(): void
    {
        Schema::table('sire_reports', function (Blueprint $table) {
            $table->enum('added_by', ['VESSEL', 'SHORE'])->default('SHORE')->after('vessel_id');
            $table->string('company_name')->nullable()->after('placeof_inspection');
            $table->string('inspector_name')->nullable()->after('company_name');
            $table->decimal('sire_cost', 12, 2)->nullable()->after('inspector_name');
            $table->string('pass_fail')->nullable()->after('sire_cost');
            $table->text('shore_remarks')->nullable()->after('pass_fail');
            $table->text('vessel_remarks')->nullable()->after('shore_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('sire_reports', function (Blueprint $table) {
            $table->dropColumn([
                'added_by', 'company_name', 'inspector_name', 'sire_cost',
                'pass_fail', 'shore_remarks', 'vessel_remarks',
            ]);
        });
    }
};
