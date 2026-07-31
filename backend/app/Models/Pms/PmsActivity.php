<?php

namespace App\Models\Pms;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsActivity extends Model
{
    protected $fillable = [
        'vessel_id',
        'pms_equipment_id',
        'pms_part_id',
        'pms_department_id',
        'spectec_main_group_id',
        'activity_code',
        'activity_name',
        'incharge',
        'work_procedure',
        'unit',
        'other_unit',
        'min_count_interval',
        'max_count_interval',
        'no_of_hours',
        'since_delivery',
        'due_date',
        'last_done',
        'previous_due_date',
        'is_postponed',
        'postpone_date',
        'monthly_done',
        'monthly_postponed',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'no_of_hours' => 'float',
            'since_delivery' => 'float',
            'due_date' => 'date',
            'last_done' => 'date',
            'previous_due_date' => 'date',
            'is_postponed' => 'boolean',
            'postpone_date' => 'date',
            'monthly_done' => 'array',
            'monthly_postponed' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(PmsEquipment::class, 'pms_equipment_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmsPart::class, 'pms_part_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(PmsDepartment::class, 'pms_department_id');
    }

    public function mainGroup(): BelongsTo
    {
        return $this->belongsTo(SpectecMainGroup::class, 'spectec_main_group_id');
    }
}
