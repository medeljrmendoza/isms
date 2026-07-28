<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CommitteeMeeting extends Model
{
    protected $fillable = [
        'vessel_id',
        'meeting_date',
        'added_by',
        'is_published',
        'is_approved',
        'is_deleted',
        'shore_remarks',
    ];

    protected function casts(): array
    {
        return [
            'meeting_date' => 'date',
            'is_published' => 'boolean',
            'is_approved' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function meetingTypes(): BelongsToMany
    {
        return $this->belongsToMany(CommitteeMeetingType::class, 'committee_meeting_meeting_type')
            ->withPivot('type_other');
    }

    /**
     * Ported from the legacy GROUP_CONCAT: each attached type's name,
     * with the "OTHERS" type's free-text detail appended in parens.
     */
    public function getMeetingTypesLabelAttribute(): string
    {
        return $this->meetingTypes
            ->map(function (CommitteeMeetingType $type) {
                if ($type->name === 'OTHERS' && $type->pivot->type_other) {
                    return "{$type->name} ({$type->pivot->type_other})";
                }

                return $type->name;
            })
            ->join(', ');
    }
}
