<?php

namespace App\Models;

use App\Models\ExposureHours\ExposureHoursRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Vessel extends Model
{
    protected $fillable = ['prefix', 'name', 'short_name', 'max_crew', 'principal_id', 'configuration'];

    public function getDisplayNameAttribute(): string
    {
        return trim("{$this->prefix} {$this->name}");
    }

    public function principal(): BelongsTo
    {
        return $this->belongsTo(Principal::class);
    }

    /**
     * Eloquent's built-in equivalent of the legacy query's
     * "join each vessel to its own MAX(date_from) record" subquery.
     */
    public function latestExposureHoursRecord(): HasOne
    {
        return $this->hasOne(ExposureHoursRecord::class)->latestOfMany('date_from');
    }
}
