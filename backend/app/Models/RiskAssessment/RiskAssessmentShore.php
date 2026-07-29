<?php

namespace App\Models\RiskAssessment;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskAssessmentShore extends Model
{
    protected $table = 'risk_assessments_shore';

    protected $fillable = [
        'report_no',
        'report_type',
        'vessel_id',
        'risk_date',
        'risk_schedule',
        'port',
        'department',
        'activity',
        'risk_category_shore_id',
        'other_category_name',
        'risk_operation_shore_id',
        'other_operation_name',
        'overall_risk',
        'remarks',
        'approval_from_shore',
        'shore_is_approved',
        'date_approved',
        'shore_remarks',
        'approval_from_marine',
        'marine_is_approved',
        'marine_date_approved',
        'marine_remarks',
        'date_closed',
    ];

    protected function casts(): array
    {
        return [
            'risk_date' => 'date',
            'risk_schedule' => 'date',
            'approval_from_shore' => 'boolean',
            'shore_is_approved' => 'boolean',
            'date_approved' => 'date',
            'approval_from_marine' => 'boolean',
            'marine_is_approved' => 'boolean',
            'marine_date_approved' => 'date',
            'date_closed' => 'date',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function riskCategoryShore(): BelongsTo
    {
        return $this->belongsTo(RiskCategoryShore::class);
    }

    public function riskOperationShore(): BelongsTo
    {
        return $this->belongsTo(RiskOperationShore::class);
    }

    public function hazards(): HasMany
    {
        return $this->hasMany(RiskAssessmentHazardShore::class)->orderBy('arrangement');
    }

    public function people(): HasMany
    {
        return $this->hasMany(RiskAssessmentPersonShore::class)->orderBy('arrangement');
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->riskCategoryShore?->name ?? $this->other_category_name ?? '';
    }

    public function getOperationLabelAttribute(): string
    {
        return $this->riskOperationShore?->name ?? $this->other_operation_name ?? '';
    }
}
