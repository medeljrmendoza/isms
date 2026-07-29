<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mapped from tb_risk_assessment_person_shore. */
    public function up(): void
    {
        Schema::create('risk_assessment_people_shore', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_assessment_shore_id')->constrained('risk_assessments_shore')->cascadeOnDelete();
            $table->unsignedInteger('arrangement')->default(1);
            $table->string('person_details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessment_people_shore');
    }
};
