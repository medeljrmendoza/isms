<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from tb_risk_assessment_shore (Controllers/Risk_assessment_
     * shore.php) — a fully separate table from the Vessel module's
     * risk_assessments, matching legacy's genuine table separation.
     * Unlike the Vessel module, this is real full CRUD: report_type
     * (SHORE or VESSEL) is chosen at creation and frozen thereafter
     * (add_report()'s edit branch always re-reads it, plus vessel_id/
     * category/operation, from the existing row rather than from POST).
     * vessel_id is only meaningful when report_type = VESSEL — a SHORE
     * report has no vessel. No master/chiefmate fields: legacy's Shore
     * add form ships them entirely commented out. No is_approved
     * column: its only consumer is a DataTable "Approve" button wired
     * to approve_riskassessment_report_shore(), a JS function that
     * doesn't exist anywhere in the four Shore view files — dead
     * reference, so nothing observable depends on this field.
     */
    public function up(): void
    {
        Schema::create('risk_assessments_shore', function (Blueprint $table) {
            $table->id();
            $table->string('report_no')->unique();
            $table->enum('report_type', ['SHORE', 'VESSEL']);
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('risk_date');
            $table->date('risk_schedule')->nullable();
            $table->string('port')->nullable();
            $table->string('department')->nullable();
            $table->string('activity')->nullable();
            $table->foreignId('risk_category_shore_id')->nullable()->constrained('risk_categories_shore')->nullOnDelete();
            $table->string('other_category_name')->nullable();
            $table->foreignId('risk_operation_shore_id')->nullable()->constrained('risk_operations_shore')->nullOnDelete();
            $table->string('other_operation_name')->nullable();
            $table->string('overall_risk')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('approval_from_shore')->default(false);
            $table->boolean('shore_is_approved')->default(false);
            $table->date('date_approved')->nullable();
            $table->text('shore_remarks')->nullable();
            $table->boolean('approval_from_marine')->default(false);
            $table->boolean('marine_is_approved')->default(false);
            $table->date('marine_date_approved')->nullable();
            $table->text('marine_remarks')->nullable();
            $table->date('date_closed')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments_shore');
    }
};
