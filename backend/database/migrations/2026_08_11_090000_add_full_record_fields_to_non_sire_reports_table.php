<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from Controllers/Non_sire.php's add_non_sire_report(). Same shape
 * as sire_reports's equivalent migration, plus inspection_type: legacy
 * sources it from a pl_non_sire_inspection_type lookup table managed under
 * Setup, which isn't migrated, so it's free text here — same treatment as
 * company_name/inspector_name (dropped Address Book FKs).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_sire_reports', function (Blueprint $table) {
            $table->enum('added_by', ['SHORE', 'VESSEL'])->default('SHORE')->after('vessel_id');
            $table->string('company_name')->nullable()->after('placeof_inspection');
            $table->string('inspector_name')->nullable()->after('company_name');
            $table->string('inspection_type')->nullable()->after('inspector_name');
            $table->decimal('sire_cost', 12, 2)->nullable()->after('inspection_type');
            $table->string('pass_fail')->nullable()->after('sire_cost');
            $table->text('shore_remarks')->nullable()->after('pass_fail');
            $table->text('vessel_remarks')->nullable()->after('shore_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('non_sire_reports', function (Blueprint $table) {
            $table->dropColumn([
                'added_by',
                'company_name',
                'inspector_name',
                'inspection_type',
                'sire_cost',
                'pass_fail',
                'shore_remarks',
                'vessel_remarks',
            ]);
        });
    }
};
