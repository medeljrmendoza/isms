<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RootCause extends Model
{
    protected $fillable = ['root_cause_category_id', 'name'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(RootCauseCategory::class, 'root_cause_category_id');
    }
}
