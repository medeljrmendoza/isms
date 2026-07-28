<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_committee_meeting table (see
     * Controllers/Dashboard_committee_meeting.php). `chairman` and
     * `incharge` are dropped — selected by legacy but not used by this
     * dashlet's filter or displayed columns.
     *
     * `shore_remarks` non-nullable with a '' default: legacy compares it
     * to '' directly (not NULL), same reasoning as other "avoid a
     * NULL-vs-empty footgun" columns elsewhere.
     */
    public function up(): void
    {
        Schema::create('committee_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('meeting_date');
            $table->enum('added_by', ['VESSEL', 'SHORE']);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->string('shore_remarks')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committee_meetings');
    }
};
