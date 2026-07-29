<?php

namespace App\Models\RiskAssessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessmentHazard extends Model
{
    protected $fillable = [
        'risk_assessment_id',
        'arrangement',
        'unwanted_consequences',
        'underlying_causes',
        'severity',
        'likelihood',
        'risk',
        'existing_control',
        'additional_control',
        're_severity',
        're_likelihood',
        're_risk',
    ];

    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class);
    }
}
