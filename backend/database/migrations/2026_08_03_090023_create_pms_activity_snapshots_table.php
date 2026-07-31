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
     * Ported (in simplified form) from tb_pms_activities_history — legacy
     * archives every per-month-named column of each activity on the
     * December-to-January rollover. A point-in-time snapshot of each
     * activity's counters at year-end, plus (as of the PMS Activities
     * module) the same monthly_done/monthly_postponed JSON maps and
     * denormalized display fields PmsActivity carries, so a past year
     * can be browsed without depending on the live activity/equipment/
     * part/department rows still existing or being unchanged.
     */
    public function up(): void
    {
        Schema::create('pms_activity_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('pms_activity_id')->nullable()->constrained('pms_activities')->nullOnDelete();
            $table->string('activity_name');
            $table->string('unit');
            $table->unsignedInteger('min_count_interval');
            $table->unsignedInteger('max_count_interval');
            $table->decimal('no_of_hours', 10, 2);
            $table->decimal('since_delivery', 10, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->unsignedSmallInteger('archived_year');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_activity_snapshots');
    }
};
