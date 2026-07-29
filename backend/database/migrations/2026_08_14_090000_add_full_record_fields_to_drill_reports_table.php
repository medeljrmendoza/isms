<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from Controllers/Drill.php's add_record(). Unlike every other
 * report module, legacy has no create-new path here at all — add_record()
 * only ever edits a drill_report row that already exists (created by the
 * unmigrated vessel-side app against a scheduled drill_list slot). So
 * there's no added_by/is_published/is_approved workflow to port either;
 * none of it exists in legacy for this module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drill_reports', function (Blueprint $table) {
            $table->string('master_name')->nullable()->after('vessel_id');
            $table->string('drill_time_from')->nullable()->after('drill_date');
            $table->string('drill_position')->nullable()->after('drill_time_from');
            $table->text('drill_details')->nullable()->after('drill_position');
            $table->text('drill_deficiencies')->nullable()->after('drill_details');
            $table->text('drill_corrective_action')->nullable()->after('drill_deficiencies');
            $table->date('report_date')->nullable()->after('drill_corrective_action');
            $table->text('vessel_remarks')->nullable()->after('report_date');
            $table->date('receipt_date')->nullable()->after('vessel_remarks');
            $table->text('shore_remarks')->nullable()->after('receipt_date');
        });
    }

    public function down(): void
    {
        Schema::table('drill_reports', function (Blueprint $table) {
            $table->dropColumn([
                'master_name',
                'drill_time_from',
                'drill_position',
                'drill_details',
                'drill_deficiencies',
                'drill_corrective_action',
                'report_date',
                'vessel_remarks',
                'receipt_date',
                'shore_remarks',
            ]);
        });
    }
};
