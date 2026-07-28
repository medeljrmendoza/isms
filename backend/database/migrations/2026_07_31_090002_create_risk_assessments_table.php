<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_risk_assessment table (see
     * Controllers/Dashboard_risk_assessment.php). Legacy stores
     * categoryID/operationID as either a real lookup-table FK *or* the
     * literal string 'OTHER' (falling back to a free-text column) —
     * a mixed-type column. Modeled more cleanly here as a nullable FK
     * plus a free-text column: NULL FK means "use the free text",
     * same behavior without the mixed-type sentinel value.
     *
     * marine_shore_is_approved renamed to marine_is_approved — the
     * legacy name reads as a typo/copy-paste of shore_is_approved.
     * No is_deleted column: legacy's own query never filters on one
     * for this table.
     */
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('report_no')->unique();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('risk_date');
            $table->foreignId('risk_category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('other_category_name')->nullable();
            $table->foreignId('risk_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->string('other_operation_name')->nullable();
            $table->boolean('approval_from_shore')->default(false);
            $table->boolean('shore_is_approved')->default(false);
            $table->boolean('approval_from_marine')->default(false);
            $table->boolean('marine_is_approved')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
