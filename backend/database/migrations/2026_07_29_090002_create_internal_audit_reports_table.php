<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_internal_audit_report table (see
     * Controllers/Dashboard_internal_audit_reports.php). Unlike Company
     * Inspections, internal audits are always vessel-specific — no
     * COMPANY-scoped variant in the legacy query — so there's no
     * vessel_company/company pair here.
     */
    public function up(): void
    {
        Schema::create('internal_audit_reports', function (Blueprint $table) {
            $table->id();
            $table->string('audit_ref')->unique();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('this_date');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_audit_reports');
    }
};
