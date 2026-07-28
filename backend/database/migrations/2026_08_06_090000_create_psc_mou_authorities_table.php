<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from legacy tb_psc_mou (referenced by Controllers/Psc.php's
     * $psc_mou dropdown data). "Others" is a real row here (matched by
     * name, like NatureOfIncident's "Other" sentinel) rather than the
     * legacy hardcoded mouID sentinel string.
     */
    public function up(): void
    {
        Schema::create('psc_mou_authorities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psc_mou_authorities');
    }
};
