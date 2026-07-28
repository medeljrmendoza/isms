<?php

namespace App\Http\Requests;

use App\Models\PscMouAuthority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/psc/add_psc_report.php's bootstrapValidator config
 * and submitHandler checks. Legacy's own JS validation is minimal
 * (ref_no, dateof_inspection, placeof_inspection required, date not in
 * the future) — the detained/released required-when rules and
 * not-greater-than-today checks on the other date fields are added here
 * to match the HTML `required` attributes on those inputs and the
 * date-field convention used consistently across every other module.
 */
class PscReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('pscReport')?->id;

        return [
            'ref_no' => [
                'required', 'string', 'max:255',
                Rule::unique('psc_reports', 'ref_no')->where(fn ($q) => $q->where('is_deleted', false))->ignore($ignoreId),
            ],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'dateof_inspection' => ['required', 'date'],
            'placeof_inspection' => ['required', 'string', 'max:255'],
            'mou_id' => ['nullable', 'exists:psc_mou_authorities,id'],
            'mou_others' => ['nullable', 'string', 'max:255'],
            'name_psco' => ['nullable', 'string', 'max:255'],
            'master_name' => ['nullable', 'string', 'max:255'],
            'chief_engineer' => ['nullable', 'string', 'max:255'],
            'is_detained' => ['boolean'],
            'detained_date' => ['nullable', 'date'],
            'detained_time' => ['nullable', 'string', 'max:255'],
            'is_released' => ['boolean'],
            'released_date' => ['nullable', 'date'],
            'released_time' => ['nullable', 'string', 'max:255'],
            'closing_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today()->toDateString();
            $isCreate = $this->isMethod('post');
            $isDetained = ! empty($data['is_detained']);
            $isReleased = ! empty($data['is_released']);

            if ($isCreate && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            if (($data['dateof_inspection'] ?? null) > $today) {
                $validator->errors()->add('dateof_inspection', 'Date of Inspection should not be greater than today.');
            }

            if (! empty($data['mou_id'])) {
                $mou = PscMouAuthority::find($data['mou_id']);
                if ($mou?->name === 'Others' && empty($data['mou_others'])) {
                    $validator->errors()->add('mou_others', 'Please specify the other authority.');
                }
            }

            if ($isDetained) {
                if (empty($data['detained_date'])) {
                    $validator->errors()->add('detained_date', 'Please input Date Detained.');
                } elseif ($data['detained_date'] > $today) {
                    $validator->errors()->add('detained_date', 'Date Detained should not be greater than today.');
                }
                if (empty($data['detained_time'])) {
                    $validator->errors()->add('detained_time', 'Please input Time Detained.');
                }

                if ($isReleased) {
                    if (empty($data['released_date'])) {
                        $validator->errors()->add('released_date', 'Please input Date Released.');
                    } elseif ($data['released_date'] > $today) {
                        $validator->errors()->add('released_date', 'Date Released should not be greater than today.');
                    }
                    if (empty($data['released_time'])) {
                        $validator->errors()->add('released_time', 'Please input Time Released.');
                    }
                }
            }

            if (! empty($data['closing_date']) && $data['closing_date'] > $today) {
                $validator->errors()->add('closing_date', 'Closing Date should not be greater than today.');
            }
        });
    }
}
