<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from legacy's pl_company_document_type. The dashlet
     * migration folded document type into company_documents.is_active
     * (see that table's docblock) since the dashlet never displays the
     * type name — the full module does (grouping/filtering by type), so
     * this reintroduces it as its own table, mirroring
     * vessel_document_types exactly.
     */
    public function up(): void
    {
        Schema::create('company_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_types');
    }
};
