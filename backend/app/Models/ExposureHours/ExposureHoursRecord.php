<?php

namespace App\Models\ExposureHours;

use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExposureHoursRecord extends Model
{
    protected $fillable = [
        'vessel_id',
        'added_by',
        'date_from',
        'date_to',
        'no_of_crew',
        'no_of_fat',
        'no_of_ptd',
        'no_of_ppd',
        'no_of_lwc',
        'no_of_rwc',
        'no_of_mtc',
        'total_hours',
        'vessel_remarks',
        'shore_remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_from' => 'date',
            'date_to' => 'date',
            'total_hours' => 'decimal:2',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
