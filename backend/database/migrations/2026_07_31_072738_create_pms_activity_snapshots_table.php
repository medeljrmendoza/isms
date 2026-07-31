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
     * December-to-January rollover. PmsActivity has no such per-month
     * columns (it's a flat vessel-level model), so this is a plain
     * point-in-time snapshot instead: a record of what each activity's
     * counters looked like at the moment a given year closed out.
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
