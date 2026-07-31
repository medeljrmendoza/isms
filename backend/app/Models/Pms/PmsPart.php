<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PmsPart extends Model
{
    protected $fillable = [
        'pms_equipment_id', 'part_code', 'part_name', 'new_qty', 'reconditioned_qty',
        'required_qty', 'unit', 'is_main', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_main' => 'boolean', 'is_active' => 'boolean'];
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(PmsEquipment::class, 'pms_equipment_id');
    }

    public function runningHours(): HasOne
    {
        return $this->hasOne(PmsRunningHoursPart::class, 'pms_parts_id');
    }
}
