<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable rather than a required FK: company_documents already has
     * seeded rows from the dashlet migration with no type assigned. New
     * rows always set one (enforced by CompanyDocumentRequest), but the
     * column itself stays nullable so existing data isn't broken.
     */
    public function up(): void
    {
        Schema::table('company_documents', function (Blueprint $table) {
            $table->foreignId('company_document_type_id')->nullable()->after('id')->constrained();
            $table->boolean('is_deleted')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('company_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_document_type_id');
            $table->dropColumn('is_deleted');
        });
    }
};
