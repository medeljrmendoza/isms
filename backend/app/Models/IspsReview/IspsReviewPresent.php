<?php

namespace App\Models\IspsReview;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IspsReviewPresent extends Model
{
    protected $fillable = ['isps_review_id', 'arrangement', 'name', 'position'];

    public function ispsReview(): BelongsTo
    {
        return $this->belongsTo(IspsReview::class);
    }
}
