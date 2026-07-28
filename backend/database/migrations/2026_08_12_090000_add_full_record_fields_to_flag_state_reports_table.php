<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from Controllers/Flag_state.php's add_flag_state_report(). Same
 * shape as external_audit_reports's equivalent migration. `inspector` is
 * plain free text in legacy (a form_input, not an Address Book FK) — no
 * dropped-FK convention needed for it, unlike SIRE/Non-SIRE.
 *
 * The dashboard-phase `ref_no` unique() is dropped for the same reason
 * as External Audits: legacy's own uniqueness check is scoped to
 * non-deleted rows only (`WHERE ref_no=? AND is_deleted=?`), so a
 * soft-deleted ref_no can be reused — enforced at the app level instead
 * via FlagStateReportRequest.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flag_state_reports', function (Blueprint $table) {
            $table->dropUnique(['ref_no']);
            $table->string('placeof_inspection')->nullable()->after('dateof_inspection');
            $table->string('inspector')->nullable()->after('placeof_inspection');
            $table->decimal('flag_cost', 12, 2)->nullable()->after('inspector');
            $table->text('shore_remarks')->nullable()->after('flag_cost');
            $table->text('vessel_remarks')->nullable()->after('shore_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('flag_state_reports', function (Blueprint $table) {
            $table->dropColumn([
                'placeof_inspection',
                'inspector',
                'flag_cost',
                'shore_remarks',
                'vessel_remarks',
            ]);
            $table->unique('ref_no');
        });
    }
};
