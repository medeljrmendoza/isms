<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_sire table (see
     * Controllers/Dashboard_sire.php). `added_by` and `bookID` are
     * dropped — both are selected by legacy but only used by the
     * omitted Actions column, not this dashlet's filter or display.
     */
    public function up(): void
    {
        Schema::create('sire_reports', function (Blueprint $table) {
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
        Schema::dropIfExists('sire_reports');
    }
};
