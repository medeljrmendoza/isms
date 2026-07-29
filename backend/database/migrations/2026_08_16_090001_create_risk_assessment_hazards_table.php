<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from tb_risk_assessment_hazzards. "re_*" columns hold the
     * post-control re-assessment scoring (legacy's "REDUCED AND FINAL
     * RA" table columns) — a distinct second round from severity/
     * likelihood/risk above.
     */
    public function up(): void
    {
        Schema::create('risk_assessment_hazards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_assessment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('arrangement')->default(1);
            $table->text('unwanted_consequences')->nullable();
            $table->text('underlying_causes')->nullable();
            $table->unsignedTinyInteger('severity')->nullable();
            $table->unsignedTinyInteger('likelihood')->nullable();
            $table->string('risk')->nullable();
            $table->text('existing_control')->nullable();
            $table->text('additional_control')->nullable();
            $table->unsignedTinyInteger('re_severity')->nullable();
            $table->unsignedTinyInteger('re_likelihood')->nullable();
            $table->string('re_risk')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessment_hazards');
    }
};
