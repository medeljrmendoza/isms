<?php

namespace App\Http\Requests\ExposureHours;

use App\Models\Vessel;
use App\Repositories\ExposureHours\ExposureHoursRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/exposurehours/add_record.php's submitHandler: date
 * from/to required and not in the future, date from must not be after
 * date to, # of crew required and must not exceed the vessel's max
 * crew, and the period must not overlap any other existing record for
 * the same vessel (legacy returns a distinct "exist" response for this
 * one — surfaced here as a normal validation error on date_from instead
 * of a special-cased response body).
 */
class ExposureHoursRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'date_from' => ['required', 'date'],
            'date_to' => ['required', 'date'],
            'no_of_crew' => ['required', 'integer', 'min:0'],
            'no_of_fat' => ['nullable', 'integer', 'min:0'],
            'no_of_ptd' => ['nullable', 'integer', 'min:0'],
            'no_of_ppd' => ['nullable', 'integer', 'min:0'],
            'no_of_lwc' => ['nullable', 'integer', 'min:0'],
            'no_of_rwc' => ['nullable', 'integer', 'min:0'],
            'no_of_mtc' => ['nullable', 'integer', 'min:0'],
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

            if (($data['date_from'] ?? null) > $today) {
                $validator->errors()->add('date_from', 'Date From should not be greater than today.');
            }

            if (($data['date_to'] ?? null) > $today) {
                $validator->errors()->add('date_to', 'Date To should not be greater than today.');
            }

            if (($data['date_from'] ?? null) > ($data['date_to'] ?? null)) {
                $validator->errors()->add('date_from', 'Date From should not be greater than Date To.');
            }

            $vesselId = $isCreate ? ($data['vessel_id'] ?? null) : $this->route('exposureHoursRecord')?->vessel_id;

            if ($vesselId) {
                $vessel = Vessel::find($vesselId);

                if ($vessel?->max_crew !== null && (int) ($data['no_of_crew'] ?? 0) > $vessel->max_crew) {
                    $validator->errors()->add('no_of_crew', "# of Crew should not be greater than {$vessel->max_crew}.");
                }

                if (! $validator->errors()->has('date_from') && ! $validator->errors()->has('date_to')
                    && ! empty($data['date_from']) && ! empty($data['date_to'])
                ) {
                    $ignoreId = $this->route('exposureHoursRecord')?->id;
                    $overlaps = app(ExposureHoursRepository::class)
                        ->overlapsExisting($vesselId, $data['date_from'], $data['date_to'], $ignoreId);

                    if ($overlaps) {
                        $validator->errors()->add('date_from', 'Date From and Date To overlaps with previous records.');
                    }
                }
            }
        });
    }
}
