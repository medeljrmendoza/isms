<?php

namespace App\Http\Requests\RiskAssessment;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/riskassessmentshore/add_risk_assessment_v.php's
 * bootstrapValidator + submitHandler checks. report_type/vessel_id/
 * category/operation are only meaningful on create — the repository
 * freezes them on update, matching legacy's edit branch, which never
 * reads them from POST.
 */
class RiskAssessmentShoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'report_type' => ['required', 'in:SHORE,VESSEL'],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'report_no' => [
                'required', 'string', 'max:255',
                Rule::unique('risk_assessments_shore', 'report_no')->ignore($this->route('riskAssessmentShore')),
            ],
            'risk_date' => ['required', 'date'],
            'risk_schedule' => ['required', 'date'],
            'port' => ['required', 'string', 'max:255'],
            'department' => ['required', 'string', 'max:255'],
            'activity' => ['required', 'in:ROUTINE,NON-ROUTINE'],
            'risk_category_shore_id' => ['nullable', 'exists:risk_categories_shore,id'],
            'other_category_name' => ['nullable', 'string', 'max:255'],
            'risk_operation_shore_id' => ['nullable', 'exists:risk_operations_shore,id'],
            'other_operation_name' => ['nullable', 'string', 'max:255'],
            'overall_risk' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
            'date_closed' => ['nullable', 'date'],

            'approval_from_shore' => ['required', 'boolean'],
            'shore_is_approved' => ['nullable', 'boolean'],
            'date_approved' => ['nullable', 'date'],
            'shore_remarks' => ['nullable', 'string'],
            'approval_from_marine' => ['required', 'boolean'],
            'marine_is_approved' => ['nullable', 'boolean'],
            'marine_date_approved' => ['nullable', 'date'],
            'marine_remarks' => ['nullable', 'string'],

            'hazards' => ['array'],
            'hazards.*.unwanted_consequences' => ['required', 'string'],
            'hazards.*.underlying_causes' => ['required', 'string'],
            'hazards.*.severity' => ['required', 'integer', 'min:1', 'max:5'],
            'hazards.*.likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'hazards.*.risk' => ['required', 'string'],
            'hazards.*.existing_control' => ['nullable', 'string'],
            'hazards.*.additional_control' => ['nullable', 'string'],
            'hazards.*.re_severity' => ['nullable', 'integer', 'min:1', 'max:5'],
            'hazards.*.re_likelihood' => ['required', 'integer', 'min:1', 'max:5'],
            'hazards.*.re_risk' => ['nullable', 'string'],

            'people' => ['array'],
            'people.*.person_details' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $isCreate = $this->isMethod('post');

            if ($isCreate && ($data['report_type'] ?? null) === 'VESSEL' && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select Vessel.');
            }

            if ($isCreate) {
                $categoryId = $data['risk_category_shore_id'] ?? null;
                $operationId = $data['risk_operation_shore_id'] ?? null;
            } else {
                $existing = $this->route('riskAssessmentShore');
                $categoryId = $existing?->risk_category_shore_id;
                $operationId = $existing?->risk_operation_shore_id;
            }

            if ($categoryId === null && empty($data['other_category_name'])) {
                $validator->errors()->add('other_category_name', 'Please specify Other Category.');
            }

            if ($operationId === null && empty($data['other_operation_name'])) {
                $validator->errors()->add('other_operation_name', 'Please specify Other Task.');
            }
        });
    }
}
