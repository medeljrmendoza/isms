<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ported from tb_vessel.crew_pass — the max crew complement, used by
 * add_record()'s "# of Crew should not exceed vessel's max crew"
 * client-side check. Nothing else in this migration reads it yet, so
 * it's added narrowly rather than as part of a fuller Vessel record.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->unsignedInteger('max_crew')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->dropColumn('max_crew');
        });
    }
};
