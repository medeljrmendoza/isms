<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lookup tables for the full Incident Report / HOR module (see
     * Controllers/Incident.php). Legacy hardcodes a few sentinel IDs
     * (e.g. "location5e45354432912") to detect the "OTHER" option in
     * each list — matched by name === 'OTHER' here instead, same
     * approach already used for NatureOfIncident's "Other"/"Collision"
     * rows.
     */
    public function up(): void
    {
        Schema::create('types_of_injury', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('locations_of_injury', function (Blueprint $table) {
            $table->id();
            $table->string('body_part');
            $table->timestamps();
        });

        Schema::create('incident_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('incident_operations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('root_cause_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('root_causes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('root_cause_category_id')->constrained();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('root_causes');
        Schema::dropIfExists('root_cause_categories');
        Schema::dropIfExists('incident_operations');
        Schema::dropIfExists('incident_locations');
        Schema::dropIfExists('locations_of_injury');
        Schema::dropIfExists('types_of_injury');
    }
};
