<?php

namespace App\Models\Drills;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DrillList extends Model
{
    protected $fillable = [
        'name',
        'drill_type',
        'frequency_type',
        'frequency_count',
        'applies_to_all_vessels',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'applies_to_all_vessels' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function vessels(): BelongsToMany
    {
        return $this->belongsToMany(Vessel::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DrillReport::class);
    }
}
