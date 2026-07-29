<?php

namespace App\Http\Requests\Drills;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/drillreports/add_drill_report.php's bootstrapValidator
 * (master/time/position notEmpty) plus its submitHandler (date required
 * and not in the future, report date required and not in the future, at
 * least one crew member with a non-empty name). This is an edit-only
 * form in legacy — there's no vessel/drill_list/drill_date-origin field
 * here at all, since none of those are ever settable from this admin.
 */
class DrillReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'master_name' => ['required', 'string', 'max:255'],
            'drill_date' => ['required', 'date'],
            'drill_time_from' => ['required', 'string', 'max:255'],
            'drill_position' => ['required', 'string', 'max:255'],
            'drill_details' => ['nullable', 'string'],
            'drill_deficiencies' => ['nullable', 'string'],
            'drill_corrective_action' => ['nullable', 'string'],
            'report_date' => ['required', 'date'],
            'vessel_remarks' => ['nullable', 'string'],
            'receipt_date' => ['nullable', 'date'],
            'shore_remarks' => ['nullable', 'string'],

            'crew' => ['required', 'array', 'min:1'],
            'crew.*.crew_name' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today()->toDateString();

            if (($data['drill_date'] ?? null) > $today) {
                $validator->errors()->add('drill_date', 'Date should not be greater than today.');
            }

            if (($data['report_date'] ?? null) > $today) {
                $validator->errors()->add('report_date', 'Report Date should not be greater than today.');
            }
        });
    }
}
