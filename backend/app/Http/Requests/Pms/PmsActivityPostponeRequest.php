<?php

namespace App\Http\Requests\Pms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/** Ported from postpone_v.php's bootstrapValidator + submitHandler. */
class PmsActivityPostponeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'postpone_date' => ['required', 'date'],
            'description' => ['required', 'string'],
            'possible_cause' => ['required', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! empty($this->input('postpone_date')) && $this->input('postpone_date') > Carbon::today()->toDateString()) {
                $validator->errors()->add('postpone_date', 'Date Postponed should not be greater than Date Today.');
            }
        });
    }
}
