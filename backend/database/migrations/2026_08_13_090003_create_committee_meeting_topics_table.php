<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from tb_committee_meeting_topics. Legacy sources topic_name from
 * a Setup-managed pl_committee_meeting_topics lookup, auto-populated by
 * an AJAX call keyed on the selected meeting type — that lookup table
 * isn't migrated, so topic_name is free text here, same convention as
 * Non-SIRE's inspection_type.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committee_meeting_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('committee_meeting_id')->constrained()->cascadeOnDelete();
            $table->string('topic_name');
            $table->text('meeting_details')->nullable();
            $table->text('meeting_comments')->nullable();
            $table->unsignedInteger('arrangement')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_meeting_topics');
    }
};
