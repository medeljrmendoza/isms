<?php

namespace App\Models\CommitteeMeetings;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommitteeMeetingTopic extends Model
{
    protected $fillable = ['committee_meeting_id', 'topic_name', 'meeting_details', 'meeting_comments', 'arrangement'];

    public function committeeMeeting(): BelongsTo
    {
        return $this->belongsTo(CommitteeMeeting::class);
    }
}
