<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_flag_state table — same shape/omissions
     * as external_audit_reports (see its migration). `inspector` and
     * `placeof_inspection` are also selected by legacy but unused by
     * this dashlet's filter or displayed columns.
     */
    public function up(): void
    {
        Schema::create('flag_state_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('dateof_inspection');
            $table->enum('added_by', ['VESSEL', 'SHORE']);
            $table->boolean('is_published')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flag_state_reports');
    }
};
