<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ported from tb_drill_report_crew — free-text crew names, order preserved via `arrangement`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drill_report_crew', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drill_report_id')->constrained()->cascadeOnDelete();
            $table->string('crew_name');
            $table->unsignedInteger('arrangement')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drill_report_crew');
    }
};
