<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_nonconformities table (see
     * Controllers/Dashboard_nonconformities.php in the legacy app), not a
     * 1:1 copy: legacy stored flags as '0'/'1' strings and used the
     * '0000-00-00' MySQL zero-date convention for "not closed" — both
     * replaced here with real booleans and nullable dates. Vessel-rename
     * history (tb_vessel_history) and the user_level-driven Edit/Approve
     * columns are intentionally not modeled — this table is read-only
     * dashboard data for now, not a full Nonconformities module.
     */
    public function up(): void
    {
        Schema::create('nonconformities', function (Blueprint $table) {
            $table->id();
            $table->string('ncr_no');
            $table->date('date_of_nc');
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('company')->nullable();
            // Whether this record is attributed to a vessel or the company/shore — purely a display concern (which name to show).
            $table->enum('vessel_company', ['VESSEL', 'COMPANY']);
            $table->text('description');
            $table->enum('added_by', ['VESSEL', 'SHORE']);
            // Non-nullable on purpose: legacy's "!= 'X' AND != 'Y'" chain
            // silently drops NULL rows under SQL's three-valued logic.
            // Manually-logged NCs use '' rather than NULL so the "not one
            // of the special sources" check behaves as actually intended.
            $table->string('source_of_nc')->default('');
            $table->boolean('is_published')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->boolean('is_inactive')->default(false);
            $table->date('close_out_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nonconformities');
    }
};
