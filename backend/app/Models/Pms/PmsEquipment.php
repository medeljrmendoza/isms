<?php

namespace App\Models\Pms;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PmsEquipment extends Model
{
    protected $fillable = ['vessel_id', 'equipment_code', 'equipment_name', 'criticality_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function parts(): HasMany
    {
        return $this->hasMany(PmsPart::class);
    }

    public function runningHours(): HasOne
    {
        return $this->hasOne(PmsRunningHoursEquipment::class);
    }

    public function criticality(): BelongsTo
    {
        return $this->belongsTo(PmsCriticality::class);
    }
}
