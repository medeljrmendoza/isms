<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_forms', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no');
            $table->string('file_name');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_deleted')->default(false);
            $table->enum('vessel_access', ['ALL', 'SPECIFIC'])->default('ALL');
            $table->string('file_hash')->nullable();
            $table->timestamps();
        });

        Schema::create('manual_form_vessel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
        });

        Schema::create('vessel_form_syncs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained();
            $table->foreignId('manual_form_id')->constrained();
            $table->string('file_hash');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_form_syncs');
        Schema::dropIfExists('manual_form_vessel');
        Schema::dropIfExists('manual_forms');
    }
};
