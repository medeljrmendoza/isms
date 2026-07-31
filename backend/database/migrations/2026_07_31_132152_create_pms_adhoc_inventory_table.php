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
        Schema::create('pms_adhoc_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_adhoc_id')->constrained('pms_adhoc')->cascadeOnDelete();
            $table->foreignId('pms_part_id')->constrained('pms_parts')->cascadeOnDelete();
            $table->unsignedInteger('new_qty')->default(0);
            $table->unsignedInteger('reconditioned_qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pms_adhoc_inventory');
    }
};
