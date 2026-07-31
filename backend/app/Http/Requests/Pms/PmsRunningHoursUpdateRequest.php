<?php

namespace App\Http\Requests\Pms;

use Illuminate\Foundation\Http\FormRequest;

/** Ported from update_running_hours()'s posted fields (rh_equipmentID, rh_date, rh_no_hours, rh_remarks). */
class PmsRunningHoursUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'equipment_id' => ['required', 'integer', 'exists:pms_equipment,id'],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.01'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
