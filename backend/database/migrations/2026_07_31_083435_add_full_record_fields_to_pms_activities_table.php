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
        Schema::table('pms_activities', function (Blueprint $table) {
            $table->string('activity_code')->nullable()->after('activity_name');
            $table->foreignId('pms_part_id')->nullable()->after('pms_equipment_id')->constrained('pms_parts')->nullOnDelete();
            $table->foreignId('pms_department_id')->nullable()->after('pms_part_id')->constrained('pms_departments')->nullOnDelete();
            $table->foreignId('spectec_main_group_id')->nullable()->after('pms_department_id')->constrained('spectec_main_groups')->nullOnDelete();
            $table->string('incharge')->nullable()->after('spectec_main_group_id');
            $table->text('work_procedure')->nullable()->after('incharge');
            $table->string('other_unit')->nullable()->after('unit');
            $table->date('last_done')->nullable()->after('due_date');
            $table->date('previous_due_date')->nullable()->after('last_done');
            $table->date('postpone_date')->nullable()->after('is_postponed');
            $table->json('monthly_done')->nullable()->after('postpone_date');
            $table->json('monthly_postponed')->nullable()->after('monthly_done');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pms_activities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pms_part_id');
            $table->dropConstrainedForeignId('pms_department_id');
            $table->dropConstrainedForeignId('spectec_main_group_id');
            $table->dropColumn([
                'activity_code',
                'incharge',
                'work_procedure',
                'other_unit',
                'last_done',
                'previous_due_date',
                'postpone_date',
                'monthly_done',
                'monthly_postponed',
            ]);
        });
    }
};
