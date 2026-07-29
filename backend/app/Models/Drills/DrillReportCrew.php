<?php

namespace App\Models\Drills;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrillReportCrew extends Model
{
    protected $table = 'drill_report_crew';

    protected $fillable = ['drill_report_id', 'crew_name', 'arrangement'];

    public function drillReport(): BelongsTo
    {
        return $this->belongsTo(DrillReport::class);
    }
}
