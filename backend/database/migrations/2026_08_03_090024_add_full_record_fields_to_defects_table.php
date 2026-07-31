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
        Schema::table('defects', function (Blueprint $table) {
            $table->text('present_status')->nullable()->after('description');
            $table->string('raised_by')->nullable()->after('present_status');
            $table->date('expected_compl_date')->nullable()->after('raised_by');
            $table->date('compl_date')->nullable()->after('expected_compl_date');
            $table->text('vessel_remarks')->nullable()->after('compl_date');
            $table->text('shore_remarks')->nullable()->after('vessel_remarks');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('defects', function (Blueprint $table) {
            $table->dropColumn([
                'present_status',
                'raised_by',
                'expected_compl_date',
                'compl_date',
                'vessel_remarks',
                'shore_remarks',
            ]);
        });
    }
};
