<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from legacy's tb_company_documentation. `date_expired` and
     * the custom-window pair are all nullable dates here (NULL = "never
     * expires" / "no custom window"), replacing the '0000-00-00' zero
     * date sentinel throughout. `attachment` (an S3 key) is dropped —
     * file viewing isn't part of this pass, same as other dashlets'
     * dropped file-attachment columns.
     */
    public function up(): void
    {
        Schema::create('company_documentation_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_document_id')->constrained()->cascadeOnDelete();
            $table->date('date_issued');
            $table->date('date_expired')->nullable();
            $table->date('date_range_from')->nullable();
            $table->date('date_range_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_documentation_records');
    }
};
