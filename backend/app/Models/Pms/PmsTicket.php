<?php

namespace App\Models\Pms;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsTicket extends Model
{
    protected $fillable = [
        'ticket_no',
        'vessel_id',
        'pms_activity_id',
        'type',
        'activity_name',
        'date_of_activity',
        'description',
        'possible_cause',
        'remarks',
        'incharge',
        'min_count_interval',
        'max_count_interval',
        'unit',
        'other_unit',
        'is_overdue',
        'equipment_name',
        'part_name',
        'previous_last_done',
        'previous_due_date',
        'reported_by',
    ];

    protected function casts(): array
    {
        return [
            'date_of_activity' => 'date',
            'is_overdue' => 'boolean',
            'previous_last_done' => 'date',
            'previous_due_date' => 'date',
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
