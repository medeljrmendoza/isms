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
        Schema::create('pms_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_equipment_id')->constrained('pms_equipment')->cascadeOnDelete();
            $table->string('part_code');
            $table->string('part_name');
            $table->boolean('is_main')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_parts');
    }
};
