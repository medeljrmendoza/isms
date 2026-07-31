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
        Schema::table('isps_reviews', function (Blueprint $table) {
            $table->foreignId('vessel_id')->nullable()->change();
            $table->foreignId('manual_document_id')->nullable()->change();
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('isps_reviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manual_chapter_id');
            $table->dropColumn(['review_description', 'review_recommendation', 'shore_reviewed_by', 'shore_remarks', 'vessel_reviewed_by', 'vessel_reviewed_position', 'vessel_remarks']);
            $table->foreignId('vessel_id')->nullable(false)->change();
            $table->foreignId('manual_document_id')->nullable(false)->change();
            $table->string('manual_section')->nullable(false)->change();
        });
    }
};
