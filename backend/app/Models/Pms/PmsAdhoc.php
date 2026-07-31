<?php

namespace App\Models\Pms;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmsAdhoc extends Model
{
    protected $table = 'pms_adhoc';

    protected $fillable = [
        'ticket_no',
        'vessel_id',
        'type',
        'pms_department_id',
        'pms_equipment_id',
        'pms_part_id',
        'location',
        'sub_location',
        'activity_name',
        'pms_job_class_id',
        'pms_job_type_id',
        'incharge',
        'assignee',
        'work_procedure',
        'date_of_activity',
        'description',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'date_of_activity' => 'date',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(PmsDepartment::class, 'pms_department_id');
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(PmsEquipment::class, 'pms_equipment_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmsPart::class, 'pms_part_id');
    }

    public function jobClass(): BelongsTo
    {
        return $this->belongsTo(PmsJobClass::class, 'pms_job_class_id');
    }

    public function jobType(): BelongsTo
    {
        return $this->belongsTo(PmsJobType::class, 'pms_job_type_id');
    }

    public function inventory(): HasMany
    {
        return $this->hasMany(PmsAdhocInventory::class);
    }
}
