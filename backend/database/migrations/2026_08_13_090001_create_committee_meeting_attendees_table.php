<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ported from tb_committee_meeting_attendance — free-text names, order preserved via `arrangement`. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_meeting_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('arrangement')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_meeting_attendees');
    }
};
