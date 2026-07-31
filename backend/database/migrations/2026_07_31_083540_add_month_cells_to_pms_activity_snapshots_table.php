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
        Schema::table('pms_activity_snapshots', function (Blueprint $table) {
            $table->string('activity_code')->nullable()->after('activity_name');
            $table->string('equipment_name')->nullable()->after('activity_code');
            $table->string('part_name')->nullable()->after('equipment_name');
            $table->string('department_name')->nullable()->after('part_name');
            $table->string('main_group_name')->nullable()->after('department_name');
            $table->string('criticality_name')->nullable()->after('main_group_name');
            $table->string('incharge')->nullable()->after('criticality_name');
            $table->json('monthly_done')->nullable()->after('due_date');
            $table->json('monthly_postponed')->nullable()->after('monthly_done');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_activity_snapshots', function (Blueprint $table) {
            $table->dropColumn([
                'activity_code',
                'equipment_name',
                'part_name',
                'department_name',
                'main_group_name',
                'criticality_name',
                'incharge',
                'monthly_done',
                'monthly_postponed',
            ]);
        });
    }
};
