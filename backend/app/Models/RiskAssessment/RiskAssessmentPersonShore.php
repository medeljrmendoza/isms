<?php

namespace App\Models\RiskAssessment;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessmentPersonShore extends Model
{
    protected $table = 'risk_assessment_people_shore';

    protected $fillable = ['risk_assessment_shore_id', 'arrangement', 'person_details'];

    public function riskAssessmentShore(): BelongsTo
    {
        return $this->belongsTo(RiskAssessmentShore::class);
    }
}
