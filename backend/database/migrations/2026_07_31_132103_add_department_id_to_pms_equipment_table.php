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
            $table->foreignId('pms_department_id')->nullable()->after('equipment_name')->constrained('pms_departments')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_equipment', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pms_department_id');
        });
    }
};
