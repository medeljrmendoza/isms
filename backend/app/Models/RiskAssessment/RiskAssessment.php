<?php

namespace App\Models\RiskAssessment;

use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiskAssessment extends Model
{
    protected $fillable = [
        'report_no',
        'vessel_id',
        'risk_date',
        'risk_category_id',
        'other_category_name',
        'risk_operation_id',
        'other_operation_name',
        'approval_from_shore',
        'shore_is_approved',
        'approval_from_marine',
        'marine_is_approved',
    ];

    protected function casts(): array
    {
        return [
            'risk_date' => 'date',
            'approval_from_shore' => 'boolean',
            'shore_is_approved' => 'boolean',
            'approval_from_marine' => 'boolean',
            'marine_is_approved' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
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
