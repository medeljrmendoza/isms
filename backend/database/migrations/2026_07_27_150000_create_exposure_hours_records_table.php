<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mapped from the legacy tb_exposure_hours_records table (see
     * Controllers/Dashboard_exposure_hours.php). Column abbreviations
     * kept as-is — FAT/PTD/PPD/LWC/RWC/MTC are standard maritime HSE
     * incident categories (fatality, permanent total/partial disability,
     * lost/restricted workday case, medical treatment case), not legacy
     * cruft worth renaming.
     */
    public function up(): void
    {
        Schema::create('exposure_hours_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vessel_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('no_of_crew')->default(0);
            $table->unsignedInteger('no_of_fat')->default(0);
            $table->unsignedInteger('no_of_ptd')->default(0);
            $table->unsignedInteger('no_of_ppd')->default(0);
            $table->unsignedInteger('no_of_lwc')->default(0);
            $table->unsignedInteger('no_of_rwc')->default(0);
            $table->unsignedInteger('no_of_mtc')->default(0);
            $table->decimal('total_hours', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exposure_hours_records');
    }
};
