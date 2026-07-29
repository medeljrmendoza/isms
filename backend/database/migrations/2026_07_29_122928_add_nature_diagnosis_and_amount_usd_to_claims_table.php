<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from Controllers/Kpi_claims.php's drill-down selects:
     * nature_diagnosis (tb_jpi_records.nature_diagnosis) and sum_usd (a
     * SUM over tb_jpi_billing_records). A full billing sub-table isn't
     * needed for a single summed dollar figure, so it's stored directly
     * on the claim, same simplification as every other single-value
     * legacy subquery in this migration.
     */
    public function up(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->text('nature_diagnosis')->nullable()->after('claims_category');
            $table->decimal('amount_usd', 12, 2)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('claims', function (Blueprint $table) {
            $table->dropColumn(['nature_diagnosis', 'amount_usd']);
        });
    }
};
