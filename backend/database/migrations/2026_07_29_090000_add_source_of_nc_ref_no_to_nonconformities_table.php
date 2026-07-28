<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a non-conformity back to the audit/inspection report it came
     * from (Company Inspection, Internal Audit, and — once built — PSC/
     * SIRE/External). Legacy joins on this as a loose string match
     * against each report table's own ref-no column, not a real FK, so
     * we keep it the same way rather than inventing a relation the
     * source data doesn't actually have.
     */
    public function up(): void
    {
        Schema::table('nonconformities', function (Blueprint $table) {
            $table->string('source_of_nc_ref_no')->nullable()->after('source_of_nc');
        });
    }

    public function down(): void
    {
        Schema::table('nonconformities', function (Blueprint $table) {
            $table->dropColumn('source_of_nc_ref_no');
        });
    }
};
