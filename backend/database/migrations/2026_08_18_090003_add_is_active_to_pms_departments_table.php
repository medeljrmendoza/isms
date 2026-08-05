<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ported from tb_pms_department.status — needed for the Setup > PMS > Department admin page's activate/inactivate toggle. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pms_departments', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('pms_departments', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
