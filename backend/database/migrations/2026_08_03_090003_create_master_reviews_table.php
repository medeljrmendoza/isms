<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('master_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('manual_document_id')->constrained();
            $table->string('manual_section');
            $table->date('review_date');
            $table->enum('added_by', ['SHORE', 'VESSEL']);
            $table->string('review_quarter');
            $table->unsignedSmallInteger('review_year');
            $table->boolean('is_deleted')->default(false);
            $table->boolean('is_vessel_approved')->default(false);
            $table->string('shore_status')->default('');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('master_reviews');
    }
};
