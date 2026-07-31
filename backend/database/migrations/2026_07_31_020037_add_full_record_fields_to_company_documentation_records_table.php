<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from Controllers/Company_documentation.php's drill-down
     * fields needed for the full add/edit/list UI (the dashlet migration
     * only needed enough for the summary counts). Not ported:
     * attachment/S3 upload handling and the per-update history archive
     * (tb_company_documentation_history) — same reasoning as Vessel
     * Documentation's identical fields. Page No. is dropped too — it
     * only feeds the printer-friendly grouped print view, not ported
     * either.
     */
    public function up(): void
    {
        Schema::table('company_documentation_records', function (Blueprint $table) {
            $table->string('doc_number')->nullable()->after('company_document_id');
            $table->string('issuing_body')->nullable()->after('doc_number');
            $table->boolean('is_printer_friendly')->default(false)->after('date_range_to');
            $table->text('remarks')->nullable()->after('is_printer_friendly');
        });
    }

    public function down(): void
    {
        Schema::table('company_documentation_records', function (Blueprint $table) {
            $table->dropColumn(['doc_number', 'issuing_body', 'is_printer_friendly', 'remarks']);
        });
    }
};
