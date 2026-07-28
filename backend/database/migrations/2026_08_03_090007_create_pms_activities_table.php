<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->string('activity_name');
            $table->enum('unit', ['H', 'D', 'W', 'M', 'Y']);
            $table->unsignedInteger('min_count_interval')->default(0);
            $table->unsignedInteger('max_count_interval')->default(0);
            $table->decimal('no_of_hours', 10, 2)->default(0);
            // Null = this activity has no running-hours meter tracked yet, so it's
            // scheduled off due_date instead. Non-null (including 0) = running-hours-based.
            $table->decimal('since_delivery', 10, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_postponed')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_activities');
    }
};
