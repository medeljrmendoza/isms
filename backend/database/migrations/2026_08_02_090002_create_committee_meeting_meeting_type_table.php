<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pivot for the meeting <-> meeting-type many-to-many. `type_other`
     * mirrors legacy's tb_committee_meeting_type.type_other — free text
     * that only applies when the attached type's name is "OTHERS",
     * appended in the display label (see CommitteeMeeting model).
     */
    public function up(): void
    {
        Schema::create('committee_meeting_meeting_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_meeting_id')->constrained()->cascadeOnDelete();
            $table->foreignId('committee_meeting_type_id')->constrained()->cascadeOnDelete();
            $table->string('type_other')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_meeting_meeting_type');
    }
};
