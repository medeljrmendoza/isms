<?php

namespace App\Models\CommitteeMeetings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeMeetingMember extends Model
{
    protected $fillable = ['committee_meeting_id', 'name', 'arrangement'];

    public function committeeMeeting(): BelongsTo
    {
        return $this->belongsTo(CommitteeMeeting::class);
    }
}
