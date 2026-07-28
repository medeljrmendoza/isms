<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drill_lists', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('frequency_type', ['D', 'W', 'M', 'Y']);
            $table->unsignedSmallInteger('frequency_count');
            $table->boolean('applies_to_all_vessels')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drill_list_vessel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drill_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drill_list_vessel');
        Schema::dropIfExists('drill_lists');
    }
};
