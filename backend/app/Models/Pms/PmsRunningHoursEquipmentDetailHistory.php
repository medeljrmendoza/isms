<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsRunningHoursEquipmentDetailHistory extends Model
{
    protected $table = 'pms_running_hours_equipment_details_history';

    protected $fillable = ['pms_running_hours_equipment_id', 'since_delivery', 'monthly_rh', 'daily_hours', 'month', 'year'];

    protected function casts(): array
    {
        return [
            'since_delivery' => 'float',
            'monthly_rh' => 'float',
            'daily_hours' => 'array',
        ];
    }

    public function runningHoursEquipment(): BelongsTo
    {
        return $this->belongsTo(PmsRunningHoursEquipment::class, 'pms_running_hours_equipment_id');
    }
}
