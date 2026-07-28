<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drill_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drill_list_id')->constrained();
            $table->foreignId('vessel_id')->constrained();
            $table->date('drill_date');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drill_reports');
    }
};
