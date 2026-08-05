<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Ported from tb_vessel.principalID/configuration — read by PMS Configuration. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->foreignId('principal_id')->nullable()->after('name')->constrained()->nullOnDelete();
            $table->string('configuration')->nullable()->after('principal_id');
        });
    }

    public function down(): void
    {
        Schema::table('vessels', function (Blueprint $table) {
            $table->dropConstrainedForeignId('principal_id');
            $table->dropColumn('configuration');
        });
    }
};
