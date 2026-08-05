<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ported from tb_pms_classification_department — which departments a classification applies to. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_classification_department', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_classification_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pms_department_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_classification_department');
    }
};
