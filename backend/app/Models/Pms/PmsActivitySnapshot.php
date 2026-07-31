<?php

namespace App\Models\Pms;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsActivitySnapshot extends Model
{
    protected $fillable = [
        'vessel_id',
        'pms_activity_id',
        'activity_name',
        'unit',
        'min_count_interval',
        'max_count_interval',
        'no_of_hours',
        'since_delivery',
        'due_date',
        'archived_year',
    ];

    protected function casts(): array
    {
        return [
            'no_of_hours' => 'float',
            'since_delivery' => 'float',
            'due_date' => 'date',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(PmsActivity::class, 'pms_activity_id');
    }
}
