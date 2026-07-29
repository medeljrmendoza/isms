<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from tb_risk_assessment_person. Legacy stores a single
     * free-text "person_details" string per row (no Address Book FK) —
     * same free-text convention used for every unmigrated Address Book
     * reference elsewhere in this app.
     */
    public function up(): void
    {
        Schema::create('risk_assessment_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('risk_assessment_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('arrangement')->default(1);
            $table->string('person_details');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessment_people');
    }
};
