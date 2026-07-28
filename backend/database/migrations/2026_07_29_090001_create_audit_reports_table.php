<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_audit_report table (see
     * Controllers/Dashboard_company_inspections.php), scoped to what
     * this dashlet filters/displays. Dropped: date_closed (selected by
     * the legacy query but never actually used in its filter or any
     * displayed column — this dashlet's "in progress" concept comes
     * entirely from having pending Nonconformities/Observations, not an
     * audit-level closed flag), the audit_type/audit_kind lookups
     * (joined but unused here), and bookID (only used to build a legacy
     * edit-form URL we don't have).
     */
    public function up(): void
    {
        Schema::create('audit_reports', function (Blueprint $table) {
            $table->id();
            $table->string('audit_ref')->unique();
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company')->nullable();
            $table->enum('vessel_company', ['VESSEL', 'COMPANY']);
            $table->date('this_date');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_reports');
    }
};
