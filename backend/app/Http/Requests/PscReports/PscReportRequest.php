<?php

namespace App\Http\Requests\PscReports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/psc/add_psc_report.php's bootstrapValidator config and
 * submitHandler checks, adapted to legacy varchar lookup IDs (tb_psc_mou)
 * instead of local numeric FKs.
 */
class PscReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ref_no' => ['required', 'string', 'max:255'],
            'vessel_id' => ['nullable', 'string'],
            'dateof_inspection' => ['required', 'date'],
            'placeof_inspection' => ['required', 'string', 'max:255'],
            'mou_id' => ['nullable', 'string'],
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
            $legacy = DB::connection('legacy');

            if ($isCreate && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            if (($data['dateof_inspection'] ?? null) > $today) {
                $validator->errors()->add('dateof_inspection', 'Date of Inspection should not be greater than today.');
            }

            if (! empty($data['mou_id'])) {
                $name = $legacy->table('tb_psc_mou')->where('mouID', $data['mou_id'])->value('mou_name');
                if ($name === 'Others' && empty($data['mou_others'])) {
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

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        foreach (['is_detained', 'is_released'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] ? '1' : '0';
            }
        }

        return $data;
    }
}
