<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from legacy's pl_company_document (document catalog) and
     * pl_company_document_type (document-type lookup) combined into one
     * table: the type lookup's own name is never displayed by this
     * dashlet, only its active/deleted flags gate whether a document
     * counts at all — folded into a single `is_active` flag here rather
     * than keeping two barely-used levels of catalog.
     */
    public function up(): void
    {
        Schema::create('company_documents', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documents');
    }
};
