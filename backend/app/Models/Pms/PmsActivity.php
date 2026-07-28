<?php

namespace App\Models\Pms;

use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsActivity extends Model
{
    protected $fillable = [
        'vessel_id',
        'activity_name',
        'unit',
        'min_count_interval',
        'max_count_interval',
        'no_of_hours',
        'since_delivery',
        'due_date',
        'is_postponed',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'no_of_hours' => 'float',
            'since_delivery' => 'float',
            'due_date' => 'date',
            'is_postponed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
