<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DrillReport extends Model
{
    protected $fillable = ['drill_list_id', 'vessel_id', 'drill_date'];

    protected function casts(): array
    {
        return [
            'drill_date' => 'date',
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
}
