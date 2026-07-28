<?php

namespace App\Http\Requests\CommitteeMeetings;

use App\Models\CommitteeMeetings\CommitteeMeetingType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/commiteemeeting/add_committee_meeting.php's
 * bootstrapValidator (position/time/chairman/incharge notEmpty) plus its
 * submitHandler (shore_vessel required, vessel required when it's
 * "VESSEL", date required and not in the future, at least one meeting
 * type selected, "Others" type requires its free-text detail).
 * shore_vessel_meeting/vessel_id are only meaningfully validated on
 * create — both freeze after that, same as vessel everywhere else.
 */
class CommitteeMeetingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shore_vessel_meeting' => ['required', 'in:SHORE,VESSEL'],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'meeting_date' => ['required', 'date'],
            'meeting_position' => ['required', 'string', 'max:255'],
            'meeting_time' => ['required', 'string', 'max:255'],
            'chairman' => ['required', 'string', 'max:255'],
            'incharge' => ['required', 'string', 'max:255'],
            'shore_remarks' => ['nullable', 'string'],

            'meeting_types' => ['required', 'array', 'min:1'],
            'meeting_types.*.committee_meeting_type_id' => ['required', 'exists:committee_meeting_types,id'],
            'meeting_types.*.type_other' => ['nullable', 'string', 'max:255'],

            'attendees' => ['array'],
            'attendees.*.name' => ['required', 'string', 'max:255'],

            'members' => ['array'],
            'members.*.name' => ['required', 'string', 'max:255'],

            'topics' => ['array'],
            'topics.*.topic_name' => ['required', 'string', 'max:255'],
            'topics.*.meeting_details' => ['nullable', 'string'],
            'topics.*.meeting_comments' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today()->toDateString();
            $isCreate = $this->isMethod('post');

            if ($isCreate && ($data['shore_vessel_meeting'] ?? null) === 'VESSEL' && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            if (($data['meeting_date'] ?? null) > $today) {
                $validator->errors()->add('meeting_date', 'Date should not be greater than today.');
            }

            foreach ($data['meeting_types'] ?? [] as $index => $row) {
                $typeId = $row['committee_meeting_type_id'] ?? null;
                $type = $typeId ? CommitteeMeetingType::find($typeId) : null;

                if ($type && $type->name === 'OTHERS' && empty($row['type_other'])) {
                    $validator->errors()->add("meeting_types.{$index}.type_other", 'Please specify the other meeting type.');
                }
            }
        });
    }
}
