<?php

namespace App\Models\RiskAssessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessmentPerson extends Model
{
    protected $fillable = ['risk_assessment_id', 'arrangement', 'person_details'];

    public function riskAssessment(): BelongsTo
    {
        return $this->belongsTo(RiskAssessment::class);
    }
}
