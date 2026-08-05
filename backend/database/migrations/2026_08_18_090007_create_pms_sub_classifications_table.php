<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ported from tb_pms_sub_classification. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pms_sub_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pms_classification_id')->constrained()->cascadeOnDelete();
            $table->string('chart_code')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pms_sub_classifications');
    }
};
