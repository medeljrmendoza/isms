<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Ported from tb_pms_running_hours — the junction marking a part
        // as running-hours-tracked. Cascades from a component-level entry
        // when its parent equipment has update_by_component=true; there's
        // no standalone entry UI for these (that's the excluded Parts
        // drill-down page).
        Schema::create('pms_running_hours_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_equipment_id')->constrained('pms_equipment');
            $table->foreignId('pms_parts_id')->unique()->constrained('pms_parts');
            $table->timestamps();
        });

        Schema::create('pms_running_hours_part_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_running_hours_parts_id')->unique()->constrained('pms_running_hours_parts')->cascadeOnDelete();
            $table->decimal('since_delivery', 10, 2)->default(0);
            $table->decimal('since_last_overhaul', 10, 2)->default(0);
            $table->date('date_last_overhauled')->nullable();
            $table->decimal('monthly_rh', 10, 2)->default(0);
            $table->json('daily_hours')->nullable();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->timestamps();
        });

        Schema::create('pms_running_hours_part_details_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_running_hours_parts_id')->constrained('pms_running_hours_parts')->cascadeOnDelete();
            $table->decimal('since_delivery', 10, 2)->default(0);
            $table->decimal('since_last_overhaul', 10, 2)->default(0);
            $table->date('date_last_overhauled')->nullable();
            $table->decimal('monthly_rh', 10, 2)->default(0);
            $table->json('daily_hours')->nullable();
            $table->unsignedTinyInteger('month');
            $table->unsignedSmallInteger('year');
            $table->timestamps();
            $table->unique(['pms_running_hours_parts_id', 'month', 'year'], 'pms_rh_part_detail_hist_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_running_hours_part_details_history');
        Schema::dropIfExists('pms_running_hours_part_details');
        Schema::dropIfExists('pms_running_hours_parts');
    }
};
