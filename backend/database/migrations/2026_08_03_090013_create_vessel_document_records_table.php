<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vessel_document_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('vessel_document_id')->constrained();
            $table->date('date_issued');
            $table->date('date_expired')->nullable();
            $table->date('date_range_from')->nullable();
            $table->date('date_range_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            // Latest known file hash on each side of the shore/vessel sync — see
            // VesselDocumentationRepository for how these drive the "new attachment" counts.
            $table->string('vessel_file_hash')->nullable();
            $table->string('shore_file_hash')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_document_records');
    }
};
