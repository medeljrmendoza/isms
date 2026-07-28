<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from legacy pl_audit_types / pl_audit_kinds (the
     * $audit_type / $audit_kind dropdowns in Controllers/Company.php).
     * Both are plain name lookups with no "Others" sentinel — unlike
     * psc_mou_authorities, the legacy form has no free-text escape
     * hatch for either, so neither needs a companion *_others column.
     */
    public function up(): void
    {
        Schema::create('audit_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('audit_kinds', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_kinds');
        Schema::dropIfExists('audit_types');
    }
};
