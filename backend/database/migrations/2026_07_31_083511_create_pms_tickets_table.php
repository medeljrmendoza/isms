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
        Schema::create('pms_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('pms_activity_id')->nullable()->constrained('pms_activities')->nullOnDelete();
            $table->enum('type', ['PLANNED', 'UNPLANNED', 'POSTPONED']);
            $table->string('activity_name');
            $table->date('date_of_activity');
            $table->text('description')->nullable();
            $table->text('possible_cause')->nullable();
            $table->text('remarks')->nullable();
            $table->string('incharge')->nullable();
            $table->unsignedInteger('min_count_interval')->default(0);
            $table->unsignedInteger('max_count_interval')->default(0);
            $table->string('unit')->nullable();
            $table->string('other_unit')->nullable();
            $table->boolean('is_overdue')->nullable();
            $table->string('equipment_name')->nullable();
            $table->string('part_name')->nullable();
            $table->date('previous_last_done')->nullable();
            $table->date('previous_due_date')->nullable();
            $table->string('reported_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_tickets');
    }
};
