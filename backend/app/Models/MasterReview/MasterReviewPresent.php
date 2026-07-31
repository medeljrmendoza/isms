<?php

namespace App\Models\MasterReview;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterReviewPresent extends Model
{
    protected $fillable = ['master_review_id', 'arrangement', 'name', 'position'];

    public function masterReview(): BelongsTo
    {
        return $this->belongsTo(MasterReview::class);
    }
}
