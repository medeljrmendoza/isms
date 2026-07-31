<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from Controllers/Master_review.php's add_record(): new
     * records created via this admin are always SHORE-added (same
     * deferral as every other module's dropped VESSEL-origin path), and
     * legacy's own Add form hides the Vessel field entirely for SHORE
     * records — vessel_id has to become nullable to represent that.
     * manual_document_id becomes nullable too: legacy lets a review
     * target either a whole chapter or one specific procedure within it
     * (manual_chapter_id, added here, is the one that's actually
     * required by the form). shore_reviewed_by is free text — legacy
     * sources it from an Address Book "office personnel" category that
     * isn't part of this migration. The four is_shore_* boolean flags
     * legacy also stores alongside shore_status aren't ported — nothing
     * reads them differently from shore_status itself (confirmed against
     * every action handler), so they'd just be a second, redundant
     * source of truth.
     */
    public function up(): void
    {
        Schema::table('master_reviews', function (Blueprint $table) {
            $table->foreignId('vessel_id')->nullable()->change();
            $table->foreignId('manual_document_id')->nullable()->change();
            // Legacy's own Section input has no required marker either
            // (unlike Procedure and Section's asterisks are both commented
            // out in add_master_review.php's markup) — a chapter-wide
            // review may have neither.
            $table->string('manual_section')->nullable()->change();
            $table->foreignId('manual_chapter_id')->nullable()->after('vessel_id')->constrained();
            $table->text('review_description')->nullable()->after('review_year');
            $table->text('review_recommendation')->nullable()->after('review_description');
            $table->string('shore_reviewed_by')->nullable()->after('review_recommendation');
            $table->text('shore_remarks')->nullable()->after('shore_reviewed_by');
            $table->string('vessel_reviewed_by')->nullable()->after('shore_remarks');
            $table->string('vessel_reviewed_position')->nullable()->after('vessel_reviewed_by');
            $table->text('vessel_remarks')->nullable()->after('vessel_reviewed_position');
        });
    }

    public function down(): void
    {
        Schema::table('master_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_chapter_id');
            $table->dropColumn([
                'review_description',
                'review_recommendation',
                'shore_reviewed_by',
                'shore_remarks',
                'vessel_reviewed_by',
                'vessel_reviewed_position',
                'vessel_remarks',
            ]);
            $table->foreignId('vessel_id')->nullable(false)->change();
            $table->foreignId('manual_document_id')->nullable(false)->change();
            $table->string('manual_section')->nullable(false)->change();
        });
    }
};
