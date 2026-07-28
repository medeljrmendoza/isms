<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from legacy's tb_company_document_expiring — a single
     * config row controlling how many months before expiry a document
     * starts showing as "expiring soon". Kept as its own table (not a
     * generic shared settings table) since legacy keeps a separate,
     * differently-named config table per document module
     * (tb_document_expiring for vessel documents, this one for
     * company documents) — same granularity here.
     */
    public function up(): void
    {
        Schema::create('company_document_expiry_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('num_month')->default(3);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_document_expiry_settings');
    }
};
