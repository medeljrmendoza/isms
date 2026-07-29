<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Legacy has a two-level hierarchy — tb_drill_type (broad category, e.g.
 * FIRE) containing several tb_drill_list rows (specific drills) — that
 * the dashboard-phase migration flattened into a single `name` with no
 * category. The calendar grid has a real TYPE column driven by it
 * (used for both display and default sort), so it's added back here as
 * free text rather than a full second lookup table + CRUD — drill types
 * are a small, stable set in practice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drill_lists', function (Blueprint $table) {
            $table->string('drill_type')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('drill_lists', function (Blueprint $table) {
            $table->dropColumn('drill_type');
        });
    }
};
