<?php

namespace App\Http\Requests\RiskAssessment;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared shape for both approval tracks (add_risk_assessment_v.php's
 * "TO BE FILLED OUT BY TECHNICAL/MARINE SUPERINTENDENT" sections):
 * approved yes/no, date approved, remarks. Used by both the
 * approve-shore and approve-marine endpoints.
 */
class RiskAssessmentApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'approved' => ['required', 'boolean'],
            'date_approved' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
