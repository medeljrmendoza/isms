<?php

namespace App\Http\Requests\Pms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** Ported from add_pms_work_plan_v.php's bootstrapValidator + submitHandler. */
class PmsAdhocRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'type' => ['required', Rule::in(['EQUIPMENT', 'LOCATION'])],
            'pms_equipment_id' => ['required_if:type,EQUIPMENT', 'nullable', 'exists:pms_equipment,id'],
            'pms_part_id' => ['nullable', 'exists:pms_parts,id'],
            'pms_department_id' => ['required_if:type,LOCATION', 'nullable', 'exists:pms_departments,id'],
            'location' => ['required_if:type,LOCATION', 'nullable', 'string', 'max:255'],
            'sub_location' => ['nullable', 'string', 'max:255'],
            'activity_name' => ['required', 'string', 'max:255'],
            'pms_job_class_id' => ['nullable', 'exists:pms_job_classes,id'],
            'pms_job_type_id' => ['nullable', 'exists:pms_job_types,id'],
            'incharge' => ['required', 'string', 'max:255'],
            'assignee' => ['nullable', 'string', 'max:255'],
            'work_procedure' => ['nullable', 'string'],
            'date_of_activity' => ['required', 'date'],
            'description' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
            'inventory' => ['nullable', 'array'],
            'inventory.*.pms_part_id' => ['required', 'exists:pms_parts,id'],
            'inventory.*.new_qty' => ['nullable', 'integer', 'min:0'],
            'inventory.*.reconditioned_qty' => ['nullable', 'integer', 'min:0'],
        ];

        if ($this->isMethod('post')) {
            $rules['vessel_id'] = ['required', 'exists:vessels,id'];
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! empty($this->input('date_of_activity')) && $this->input('date_of_activity') > Carbon::today()->toDateString()) {
                $validator->errors()->add('date_of_activity', 'Date of Activity should not be greater than Date Today.');
            }
        });
    }
}
