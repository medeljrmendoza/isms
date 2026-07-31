<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from Controllers/Vessel_documentation.php's tb_vessel_documentation
     * fields needed for the full add/edit/list UI (the dashlet migration only
     * needed enough for the summary counts). Not ported: attachment/file_hash
     * upload handling (no S3 infra anywhere in this migration) and the
     * per-update history archive (tb_vessel_documentation_history_shore),
     * which exists purely to track past attachment versions.
     */
    public function up(): void
    {
        Schema::table('vessel_document_records', function (Blueprint $table) {
            $table->string('doc_number')->nullable()->after('vessel_document_id');
            $table->string('issuing_body')->nullable()->after('doc_number');
            $table->boolean('is_printer_friendly')->default(false)->after('date_range_to');
            $table->text('shore_remarks')->nullable()->after('is_printer_friendly');
            $table->text('vessel_remarks')->nullable()->after('shore_remarks');
        });
    }

    public function down(): void
    {
        Schema::table('vessel_document_records', function (Blueprint $table) {
            $table->dropColumn(['doc_number', 'issuing_body', 'is_printer_friendly', 'shore_remarks', 'vessel_remarks']);
        });
    }
};
