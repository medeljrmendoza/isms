<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_natureof_incident lookup table. Legacy
     * special-cased two specific rows by their (arbitrary, uniqid-style)
     * primary key string — "Other" and "Collision" — to know when to
     * append a free-text detail. We match by `name` instead (see
     * IncidentReportRepository), since a fresh table won't share those
     * legacy IDs.
     */
    public function up(): void
    {
        Schema::create('nature_of_incidents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nature_of_incidents');
    }
};
