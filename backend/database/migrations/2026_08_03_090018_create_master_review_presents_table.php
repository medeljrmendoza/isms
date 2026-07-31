<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from legacy's tb_master_review_present — attendees present
     * during the review, freely added/removed per record (no min/max),
     * same shape as CommitteeMeeting's attendee sub-table.
     */
    public function up(): void
    {
        Schema::create('master_review_presents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_review_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('arrangement')->default(1);
            $table->string('name');
            $table->string('position')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_review_presents');
    }
};
