<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_incident_report table (see
     * Controllers/Dashboard_incident.php), scoped to what the dashlet
     * displays and filters on. Dropped: vessel-rename history resolution
     * (same as Nonconformities), published/report_status/location/
     * ship_operation fields (unused by this dashlet's filter or columns),
     * and the user_level-driven Actions column (read-only for now).
     */
    public function up(): void
    {
        Schema::create('incident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('dateof_report');
            $table->enum('nature_type', ['accident', 'hazardous_occurrence']);
            $table->foreignId('nature_of_incident_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hazardous_occurrence_type')->nullable();
            // Free-text detail, only meaningful when nature_of_incident's name is "Other" or "Collision" respectively.
            $table->string('others')->nullable();
            $table->string('accident_collision')->nullable();
            $table->date('closing_date')->nullable();
            $table->boolean('is_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_reports');
    }
};
