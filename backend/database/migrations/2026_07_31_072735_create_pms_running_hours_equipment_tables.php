<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/Pms_running_hours_equipments.php. Legacy
     * stores one physical column per day of the month (d1..d31); this
     * uses a `daily_hours` JSON map ({"1": 4.5, ...}) instead — same
     * data, far less boilerplate, no behavioral difference.
     */
    public function up(): void
    {
        Schema::create('pms_running_hours_equipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('pms_equipment_id')->unique()->constrained('pms_equipment');
            // False = this component's hours are only ever entered at the
            // individual-part level (legacy's Parts drill-down page, not
            // part of this migration) — this screen shows it blank.
            $table->boolean('update_by_component')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pms_running_hours_equipment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_running_hours_equipment_id')->unique()->constrained('pms_running_hours_equipment')->cascadeOnDelete();
            $table->decimal('since_delivery', 10, 2)->default(0);
            $table->decimal('monthly_rh', 10, 2)->default(0);
            $table->json('daily_hours')->nullable();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->timestamps();
        });

        Schema::create('pms_running_hours_equipment_details_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_running_hours_equipment_id')->constrained('pms_running_hours_equipment')->cascadeOnDelete();
            $table->decimal('since_delivery', 10, 2)->default(0);
            $table->decimal('monthly_rh', 10, 2)->default(0);
            $table->json('daily_hours')->nullable();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->timestamps();
            $table->unique(['pms_running_hours_equipment_id', 'month', 'year'], 'pms_rh_equip_detail_hist_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_running_hours_equipment_details_history');
        Schema::dropIfExists('pms_running_hours_equipment_details');
        Schema::dropIfExists('pms_running_hours_equipment');
    }
};
