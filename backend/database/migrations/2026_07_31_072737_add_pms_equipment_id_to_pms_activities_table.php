<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * Ported from tb_pms_activities.equipmentID — links an activity to the
     * component whose running-hours entries feed its no_of_hours counter.
     * Legacy also allows linking at the partsID level (with sbID-based
     * grouping); PmsActivity has no such granularity, so this stays
     * equipment-level only.
     */
    public function up(): void
    {
        Schema::table('pms_activities', function (Blueprint $table) {
            $table->foreignId('pms_equipment_id')->nullable()->after('vessel_id')->constrained('pms_equipment');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pms_equipment_id');
        });
    }
};
