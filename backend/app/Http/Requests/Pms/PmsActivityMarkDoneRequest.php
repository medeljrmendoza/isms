<?php

namespace App\Http\Requests\Pms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/** Ported from update_activity_v.php's bootstrapValidator + submitHandler. */
class PmsActivityMarkDoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'last_done' => ['required', 'date'],
            'unplanned' => ['required', 'boolean'],
            'unplanned_description' => ['required_if:unplanned,1', 'nullable', 'string'],
            'unplanned_cause' => ['required_if:unplanned,1', 'nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if (! empty($this->input('last_done')) && $this->input('last_done') > Carbon::today()->toDateString()) {
                $validator->errors()->add('last_done', 'Date of Activity should not be greater than Date Today.');
            }
        });
    }
}
