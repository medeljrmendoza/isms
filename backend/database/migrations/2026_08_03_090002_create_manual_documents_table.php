<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_chapter_id')->constrained();
            $table->string('reference_no');
            $table->string('manual_name');
            $table->date('date_of_revision');
            $table->boolean('is_published')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_documents');
    }
};
