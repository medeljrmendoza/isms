<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_psc_report table (see
     * Controllers/Dashboard_psc.php). Same shape/omissions as
     * audit_reports and internal_audit_reports — closing_date and
     * bookID are selected by legacy but unused by this dashlet's filter
     * or columns.
     */
    public function up(): void
    {
        Schema::create('psc_reports', function (Blueprint $table) {
            $table->id();
            $table->string('ref_no')->unique();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('dateof_inspection');
            $table->boolean('is_deleted')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psc_reports');
    }
};
