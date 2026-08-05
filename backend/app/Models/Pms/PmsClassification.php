<?php

namespace App\Models\Pms;

use App\Models\VesselType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PmsClassification extends Model
{
    protected $fillable = ['name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(PmsDepartment::class, 'pms_classification_department');
    }

    public function vesselTypes(): BelongsToMany
    {
        return $this->belongsToMany(VesselType::class, 'pms_classification_vessel_type');
    }

    public function subClassifications(): HasMany
    {
        return $this->hasMany(PmsSubClassification::class);
    }
}
