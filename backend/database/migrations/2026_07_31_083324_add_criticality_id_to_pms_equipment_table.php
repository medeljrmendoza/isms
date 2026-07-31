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
        Schema::table('pms_equipment', function (Blueprint $table) {
            $table->foreignId('criticality_id')->nullable()->after('equipment_name')->constrained('pms_criticalities')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_equipment', function (Blueprint $table) {
            $table->dropConstrainedForeignId('criticality_id');
        });
    }
};
