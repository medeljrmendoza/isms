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
        Schema::create('pms_adhoc', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_no')->unique();
            $table->foreignId('vessel_id')->constrained();
            $table->enum('type', ['EQUIPMENT', 'LOCATION']);
            $table->foreignId('pms_department_id')->nullable()->constrained('pms_departments')->nullOnDelete();
            $table->foreignId('pms_equipment_id')->nullable()->constrained('pms_equipment')->nullOnDelete();
            $table->foreignId('pms_part_id')->nullable()->constrained('pms_parts')->nullOnDelete();
            $table->string('location')->nullable();
            $table->string('sub_location')->nullable();
            $table->string('activity_name');
            $table->foreignId('pms_job_class_id')->nullable()->constrained('pms_job_classes')->nullOnDelete();
            $table->foreignId('pms_job_type_id')->nullable()->constrained('pms_job_types')->nullOnDelete();
            $table->string('incharge');
            $table->string('assignee')->nullable();
            $table->text('work_procedure')->nullable();
            $table->date('date_of_activity');
            $table->text('description')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_adhoc');
    }
};
