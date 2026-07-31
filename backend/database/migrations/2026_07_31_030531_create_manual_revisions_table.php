<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('manual_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_document_id')->constrained();
            $table->unsignedInteger('arrangement');
            $table->string('revision_no');
            $table->date('date_revised');
            $table->string('section')->nullable();
            $table->text('reason_revision')->nullable();
            $table->string('reviewed_by');
            $table->string('approved_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_revisions');
    }
};
