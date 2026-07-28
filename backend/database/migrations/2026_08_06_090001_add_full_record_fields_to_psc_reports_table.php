<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ported from Controllers/Psc.php's add_psc_report(). Not ported:
     * bookID/SIRE observation linkage (no Observations module exists),
     * file attachments (no file storage anywhere in this app). master_id
     * is renamed master_name — despite the "_id" suffix, legacy always
     * stores free text there, never a foreign key.
     *
     * The dashboard-phase `ref_no` unique() constraint is dropped here:
     * legacy's own duplicate check is scoped to non-deleted rows
     * (`WHERE ref_no=? AND is_deleted='0'`), so a ref_no from a
     * previously-deleted report must be reusable. That scoped rule is
     * enforced at the FormRequest layer instead (PscReportRequest).
     */
    public function up(): void
    {
        Schema::table('psc_reports', function (Blueprint $table) {
            $table->dropUnique(['ref_no']);

            $table->string('placeof_inspection')->nullable()->after('dateof_inspection');
            $table->foreignId('mou_id')->nullable()->after('placeof_inspection')->constrained('psc_mou_authorities')->nullOnDelete();
            $table->string('mou_others')->nullable()->after('mou_id');
            $table->string('name_psco')->nullable()->after('mou_others');
            $table->string('master_name')->nullable()->after('name_psco');
            $table->string('chief_engineer')->nullable()->after('master_name');
            $table->boolean('is_detained')->default(false)->after('chief_engineer');
            $table->date('detained_date')->nullable()->after('is_detained');
            $table->string('detained_time')->nullable()->after('detained_date');
            $table->boolean('is_released')->default(false)->after('detained_time');
            $table->date('released_date')->nullable()->after('is_released');
            $table->string('released_time')->nullable()->after('released_date');
            $table->date('closing_date')->nullable()->after('released_time');
            $table->text('remarks')->nullable()->after('closing_date');
        });
    }

    public function down(): void
    {
        Schema::table('psc_reports', function (Blueprint $table) {
            $table->dropForeign(['mou_id']);
            $table->dropColumn([
                'placeof_inspection', 'mou_id', 'mou_others', 'name_psco', 'master_name', 'chief_engineer',
                'is_detained', 'detained_date', 'detained_time', 'is_released', 'released_date', 'released_time',
                'closing_date', 'remarks',
            ]);
            $table->unique('ref_no');
        });
    }
};
