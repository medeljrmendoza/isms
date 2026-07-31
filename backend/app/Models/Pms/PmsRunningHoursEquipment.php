<?php

namespace App\Models\Pms;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PmsRunningHoursEquipment extends Model
{
    protected $fillable = ['vessel_id', 'pms_equipment_id', 'update_by_component', 'is_active'];

    protected function casts(): array
    {
        return ['update_by_component' => 'boolean', 'is_active' => 'boolean'];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(PmsEquipment::class, 'pms_equipment_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(PmsRunningHoursEquipmentDetail::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(PmsRunningHoursEquipmentDetailHistory::class);
    }
}
