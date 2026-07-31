<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pms_parts', function (Blueprint $table) {
            $table->unsignedInteger('new_qty')->default(0)->after('part_name');
            $table->unsignedInteger('reconditioned_qty')->default(0)->after('new_qty');
            $table->unsignedInteger('required_qty')->nullable()->after('reconditioned_qty');
            $table->string('unit')->nullable()->after('required_qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_parts', function (Blueprint $table) {
            $table->dropColumn(['new_qty', 'reconditioned_qty', 'required_qty', 'unit']);
        });
    }
};
