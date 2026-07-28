<?php

namespace App\Models\IncidentReports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RootCauseCategory extends Model
{
    protected $fillable = ['name'];

    public function rootCauses(): HasMany
    {
        return $this->hasMany(RootCause::class);
    }
}
