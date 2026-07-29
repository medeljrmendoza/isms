<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mapped from tb_risk_operation_shore. See risk_categories_shore's docblock. */
    public function up(): void
    {
        Schema::create('risk_operations_shore', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_operations_shore');
    }
};
