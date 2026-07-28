<?php

namespace App\Http\Requests\FlagState;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/flagstate/add_flag_state.php's submitHandler + the
 * `flag_state_ref_no: notEmpty` rule in its `fields` block. Vessel is
 * required on create only (checked in withValidator, like every other
 * report module). ref_no uniqueness is scoped to non-deleted rows —
 * legacy's own check is `WHERE ref_no=? AND is_deleted=?` — so a
 * soft-deleted ref_no can be reused, same convention as Company/PSC/
 * Internal/External Audits.
 */
class FlagStateReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('flagStateReport')?->id;

        return [
            'ref_no' => [
                'required', 'string', 'max:255',
                Rule::unique('flag_state_reports', 'ref_no')->where(fn ($q) => $q->where('is_deleted', false))->ignore($ignoreId),
            ],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'dateof_inspection' => ['required', 'date'],
            'placeof_inspection' => ['nullable', 'string', 'max:255'],
            'inspector' => ['nullable', 'string', 'max:255'],
            'flag_cost' => ['nullable', 'numeric', 'min:0'],
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
