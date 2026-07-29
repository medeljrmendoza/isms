<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from Controllers/Exposure_hours.php's add_record(). added_by is
 * always "SHORE" on insert and frozen on edit — no VESSEL-origin
 * creation path is reachable from this admin, same as every other
 * report module.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('exposure_hours_records', function (Blueprint $table) {
            $table->enum('added_by', ['SHORE', 'VESSEL'])->default('SHORE')->after('vessel_id');
            $table->text('vessel_remarks')->nullable()->after('total_hours');
            $table->text('shore_remarks')->nullable()->after('vessel_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('exposure_hours_records', function (Blueprint $table) {
            $table->dropColumn(['added_by', 'vessel_remarks', 'shore_remarks']);
        });
    }
};
