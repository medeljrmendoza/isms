<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsRunningHoursPartDetail extends Model
{
    protected $fillable = [
        'pms_running_hours_parts_id',
        'since_delivery',
        'since_last_overhaul',
        'date_last_overhauled',
        'monthly_rh',
        'daily_hours',
        'month',
        'year',
    ];

    protected function casts(): array
    {
        return [
            'since_delivery' => 'float',
            'since_last_overhaul' => 'float',
            'date_last_overhauled' => 'date',
            'monthly_rh' => 'float',
            'daily_hours' => 'array',
        ];
    }

    public function runningHoursPart(): BelongsTo
    {
        return $this->belongsTo(PmsRunningHoursPart::class, 'pms_running_hours_parts_id');
    }
}
