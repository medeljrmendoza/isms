<?php

namespace App\Models\RiskAssessment;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RiskAssessment extends Model
{
    protected $fillable = [
        'report_no',
        'vessel_id',
        'risk_date',
        'risk_schedule',
        'port',
        'department',
        'activity',
        'risk_category_id',
        'other_category_name',
        'risk_operation_id',
        'other_operation_name',
        'overall_risk',
        'master',
        'ce_co',
        'vessel_remarks',
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

    public function hazards(): HasMany
    {
        return $this->hasMany(RiskAssessmentHazard::class)->orderBy('arrangement');
    }

    public function people(): HasMany
    {
        return $this->hasMany(RiskAssessmentPerson::class)->orderBy('arrangement');
    }

    public function riskCategory(): BelongsTo
    {
        return $this->belongsTo(RiskCategory::class);
    }

    public function riskOperation(): BelongsTo
    {
        return $this->belongsTo(RiskOperation::class);
    }

    public function getCategoryLabelAttribute(): string
    {
        return $this->riskCategory?->name ?? $this->other_category_name ?? '';
    }

    public function getOperationLabelAttribute(): string
    {
        return $this->riskOperation?->name ?? $this->other_operation_name ?? '';
    }
}
