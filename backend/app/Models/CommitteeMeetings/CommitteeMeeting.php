<?php

namespace App\Models\CommitteeMeetings;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommitteeMeeting extends Model
{
    protected $fillable = [
        'vessel_id',
        'meeting_date',
        'added_by',
        'shore_vessel_meeting',
        'meeting_position',
        'meeting_time',
        'chairman',
        'incharge',
        'vessel_remarks',
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

    public function attendees(): HasMany
    {
        return $this->hasMany(CommitteeMeetingAttendee::class)->orderBy('arrangement');
    }

    public function members(): HasMany
    {
        return $this->hasMany(CommitteeMeetingMember::class)->orderBy('arrangement');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(CommitteeMeetingTopic::class)->orderBy('arrangement');
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
