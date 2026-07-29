<?php

namespace App\Models\RiskAssessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessmentHazardShore extends Model
{
    protected $table = 'risk_assessment_hazards_shore';

    protected $fillable = [
        'risk_assessment_shore_id',
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

    public function riskAssessmentShore(): BelongsTo
    {
        return $this->belongsTo(RiskAssessmentShore::class);
    }
}
