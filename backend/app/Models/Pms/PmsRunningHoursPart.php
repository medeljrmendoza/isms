<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PmsRunningHoursPart extends Model
{
    protected $fillable = ['pms_equipment_id', 'pms_parts_id'];

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(PmsEquipment::class, 'pms_equipment_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmsPart::class, 'pms_parts_id');
    }

    public function detail(): HasOne
    {
        return $this->hasOne(PmsRunningHoursPartDetail::class, 'pms_running_hours_parts_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(PmsRunningHoursPartDetailHistory::class, 'pms_running_hours_parts_id');
    }
}
