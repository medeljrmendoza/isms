<?php

namespace App\Http\Requests\Sire;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/sire/add_sire.php's submitHandler — bootstrapValidator's
 * own `fields` block is empty; every rule lives in the custom handler.
 * Vessel is required on create only. Legacy also requires the SIRE book
 * on create, but that's part of the dropped Observations/SIRE-book
 * linkage, so there's nothing to require here. Unlike every other
 * report module, there's no ref_no at all, so no uniqueness rule either.
 */
class SireReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'dateof_inspection' => ['required', 'date'],
            'placeof_inspection' => ['nullable', 'string', 'max:255'],
            'company_name' => ['nullable', 'string', 'max:255'],
            'inspector_name' => ['nullable', 'string', 'max:255'],
            'sire_cost' => ['nullable', 'numeric', 'min:0'],
            'pass_fail' => ['nullable', 'in:PASS,FAIL,N/A'],
            'shore_remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today()->toDateString();
            $isCreate = $this->isMethod('post');

            if ($isCreate && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            if (($data['dateof_inspection'] ?? null) > $today) {
                $validator->errors()->add('dateof_inspection', 'Date of Inspection should not be greater than today.');
            }
        });
    }
}
