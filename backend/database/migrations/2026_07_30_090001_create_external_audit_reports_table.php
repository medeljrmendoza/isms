<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_external_audit_report table (see
     * Controllers/Dashboard_external.php). Unlike the other audit-style
     * dashlets, this one also gates on an approval workflow
     * (added_by/is_published/is_approved), independent of whether the
     * report has any pending non-conformities — see
     * ExternalAuditReportRepository for the filter itself.
     */
    public function up(): void
    {
        Schema::create('external_audit_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('dateof_audit');
            $table->enum('added_by', ['VESSEL', 'SHORE']);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_audit_reports');
    }
};
