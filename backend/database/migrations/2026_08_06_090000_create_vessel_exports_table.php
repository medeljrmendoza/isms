<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_vessel_export table (see
     * Controllers/Dashboard.php's loadImportData()/export()). Only the
     * export-file log is ported — the actual zip export/import
     * mechanics depend on a vessel-side sync application that isn't
     * part of this migration.
     */
    public function up(): void
    {
        Schema::create('vessel_exports', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('vessel_file');
            $table->foreignId('vessel_id')->nullable()->constrained()->nullOnDelete();
            $table->date('date_of_export');
            // Legacy's "flag": 1 once the vessel confirms receipt, 0 while pending.
            $table->boolean('status')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vessel_exports');
    }
};
