<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from tb_risk_category_shore — a Setup-managed lookup fully
     * separate from the Vessel module's risk_categories table, matching
     * legacy's genuinely separate table (and separate Setup screen).
     */
    public function up(): void
    {
        Schema::create('risk_categories_shore', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_categories_shore');
    }
};
