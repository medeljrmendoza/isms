<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_documents', function (Blueprint $table) {
            $table->enum('vessel_access', ['ALL', 'SPECIFIC'])->default('ALL')->after('is_published');
            $table->string('file_hash')->nullable()->after('vessel_access');
        });

        Schema::create('manual_document_vessel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('vessel_manual_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('manual_document_id')->constrained();
            $table->string('file_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_manual_syncs');
        Schema::dropIfExists('manual_document_vessel');
        Schema::table('manual_documents', function (Blueprint $table) {
            $table->dropColumn(['vessel_access', 'file_hash']);
        });
    }
};
