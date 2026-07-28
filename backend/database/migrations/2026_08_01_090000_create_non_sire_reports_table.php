<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mapped from the legacy tb_non_sire table — same shape/omissions as sire_reports (see its migration). */
    public function up(): void
    {
        Schema::create('non_sire_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('dateof_inspection');
            $table->string('placeof_inspection');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('non_sire_reports');
    }
};
