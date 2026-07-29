<?php

namespace App\Models\Drills;

use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrillReport extends Model
{
    protected $fillable = [
        'drill_list_id',
        'vessel_id',
        'master_name',
        'drill_date',
        'drill_time_from',
        'drill_position',
        'drill_details',
        'drill_deficiencies',
        'drill_corrective_action',
        'report_date',
        'vessel_remarks',
        'receipt_date',
        'shore_remarks',
    ];

    protected function casts(): array
    {
        return [
            'drill_date' => 'date',
            'report_date' => 'date',
            'receipt_date' => 'date',
        ];
    }

    public function drillList(): BelongsTo
    {
        return $this->belongsTo(DrillList::class);
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function crew(): HasMany
    {
        return $this->hasMany(DrillReportCrew::class)->orderBy('arrangement');
    }
}
