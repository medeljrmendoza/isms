<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_jpi_records table (see
     * Controllers/Dashboard_claims.php). Renamed to `claims` — "JPI" is
     * internal legacy jargon; the UI itself just calls this "Claims".
     * user_vessel-based scoping is dropped, same deferral as
     * Nonconformities.
     */
    public function up(): void
    {
        Schema::create('claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_no');
            $table->string('claims_category');
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('report_date');
            // Non-nullable with a default for the same reason as
            // Nonconformity::source_of_nc — avoids "status != 'CLOSED'"
            // silently excluding NULL rows under SQL's three-valued logic.
            $table->string('status')->default('OPEN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('claims');
    }
};
