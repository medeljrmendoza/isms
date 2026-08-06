<?php

namespace App\Http\Requests\Defects;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ported from add_defect_list.php's bootstrapValidator + submitHandler:
 * SL No./Date/Compl Code carry a required asterisk in the form; the
 * "required" attribute copy-pasted onto the Expected Compl Date/Compl
 * Date inputs is never actually enforced by the submit handler, so both
 * stay optional here too. vessel_id is required on create only — the
 * legacy Add form's disabled "defect_vessel_name" field has no `name`
 * attribute and is never submitted, so a fresh legacy record silently
 * gets vesID="" on create; here Vessel is a real, required field
 * instead, and controller freezes it on edit.
 */
class DefectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'sl_no' => ['required', 'string', 'max:255'],
            'defect_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'present_status' => ['nullable', 'string'],
            'priority' => ['nullable', Rule::in(['1', '2', '3'])],
            'category' => ['nullable', Rule::in(['N', 'T', 'O'])],
            'raised_by' => ['nullable', Rule::in(['VSL', 'VIR', 'IAR', 'INC', 'TPR'])],
            'compl_code' => ['required', Rule::in(['P', 'I', 'C', 'H', 'D'])],
            'expected_compl_date' => ['nullable', 'date'],
            'compl_date' => ['nullable', 'date'],
            'shore_remarks' => ['nullable', 'string'],
        ];

        if ($this->isMethod('post')) {
            $rules['vessel_id'] = ['required', 'string'];
        }

        return $rules;
    }
}
