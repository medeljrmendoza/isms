<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/nonconformities/add_nonconformity.php's client-side
 * bootstrapValidator config and submitHandler checks. Used for both
 * create and update — vessel_id is only required on create because
 * legacy freezes vessel/company attribution after that (see
 * NonconformityRepository::update()).
 */
class NonconformityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ncr_no' => ['required', 'string', 'max:255'],
            'date_of_nc' => ['required', 'date'],
            'vessel_company' => ['required', 'in:VESSEL,COMPANY'],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'company' => ['nullable', 'string', 'max:255'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'reported_by' => ['required', 'in:SHORE,VESSEL'],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'source_of_nc' => ['required', 'in:OPERATIONAL,OTHERS'],
            'source_of_nc_others' => ['nullable', 'string', 'max:255'],
            'source_of_nc_ref_no' => ['nullable', 'string', 'max:255'],
            'manual_chapter_id' => ['nullable', 'exists:manual_chapters,id'],
            'sms_details' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'root_cause' => ['nullable', 'string'],
            'root_cause_incharge' => ['nullable', 'string', 'max:255'],
            'corrective_action' => ['nullable', 'string'],
            'corrective_action_incharge' => ['nullable', 'string', 'max:255'],
            'corrective_action_date' => ['nullable', 'date'],
            'verification' => ['nullable', 'in:COMPLETED,FOLLOW-UP,ASSISTANCE'],
            'verification_followup' => ['nullable', 'string'],
            'verification_assistance' => ['nullable', 'string'],
            'verification_dpa' => ['nullable', 'string', 'max:255'],
            'verification_date' => ['nullable', 'date'],
            'close_out_completed' => ['boolean'],
            'close_out_followup' => ['boolean'],
            'close_out_followup_nature' => ['nullable', 'string'],
            'close_out_dpa' => ['nullable', 'string', 'max:255'],
            'close_out_date' => ['nullable', 'date'],
            'attach_safety_meeting' => ['boolean'],
            'attach_record_training' => ['boolean'],
            'attach_logbook' => ['boolean'],
            'attach_delivery_note' => ['boolean'],
            'attach_photo' => ['boolean'],
            'attach_company_forms' => ['boolean'],
            'attach_others' => ['boolean'],
            'attach_others_details' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today()->toDateString();
            $isCreate = $this->isMethod('post');

            if (($data['date_of_nc'] ?? null) > $today) {
                $validator->errors()->add('date_of_nc', 'Date of NC should not be greater than today.');
            }

            if ($isCreate && ($data['vessel_company'] ?? null) === 'VESSEL' && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            if (($data['vessel_company'] ?? null) === 'COMPANY' && empty($data['company'])) {
                $validator->errors()->add('company', 'Please input a company.');
            }

            if (($data['source_of_nc'] ?? null) === 'OTHERS' && empty($data['source_of_nc_others'])) {
                $validator->errors()->add('source_of_nc_others', 'Please input the other source of non-conformity.');
            }

            $hasCorrectiveAction = ! empty($data['corrective_action']);
            $hasCorrectiveActionDate = ! empty($data['corrective_action_date']);

            if ($hasCorrectiveAction && ! $hasCorrectiveActionDate) {
                $validator->errors()->add('corrective_action_date', 'Proposed Corrective Action is filled out, please input Target Date of Completion.');
            }
            if (! $hasCorrectiveAction && $hasCorrectiveActionDate) {
                $validator->errors()->add('corrective_action', 'Target Date of Completion is filled out, please input Proposed Corrective Action.');
            }
            if ($hasCorrectiveActionDate && ($data['corrective_action_date'] < ($data['date_of_nc'] ?? null))) {
                $validator->errors()->add('corrective_action_date', 'Target Date of Completion should not be less than Date of NC.');
            }

            $hasVerification = ! empty($data['verification']);
            $hasVerificationDpa = ! empty($data['verification_dpa']);
            $hasVerificationDate = ! empty($data['verification_date']);

            if (! $hasVerification) {
                if ($hasVerificationDpa) {
                    $validator->errors()->add('verification_dpa', 'DPA is filled out, please select a Verification of Corrective Action option.');
                }
                if ($hasVerificationDate) {
                    $validator->errors()->add('verification_date', 'Verification Date is filled out, please select a Verification of Corrective Action option.');
                }
            } else {
                if (! $hasVerificationDpa) {
                    $validator->errors()->add('verification_dpa', 'Please input DPA / Safety Management Committee.');
                }
                if (! $hasVerificationDate) {
                    $validator->errors()->add('verification_date', 'Please input Verification Date.');
                }
            }
            if ($hasVerificationDate && $data['verification_date'] > $today) {
                $validator->errors()->add('verification_date', 'Verification Date should not be greater than today.');
            }

            $closeOutSelected = ! empty($data['close_out_completed']) || ! empty($data['close_out_followup']);
            $hasCloseOutDpa = ! empty($data['close_out_dpa']);
            $hasCloseOutDate = ! empty($data['close_out_date']);

            if ($closeOutSelected) {
                if (! $hasCloseOutDpa) {
                    $validator->errors()->add('close_out_dpa', 'Close Out has been selected, please input Designated Person Ashore.');
                }
                if (! $hasCloseOutDate) {
                    $validator->errors()->add('close_out_date', 'Close Out has been selected, please input Close Out Date.');
                }
            } else {
                if ($hasCloseOutDpa) {
                    $validator->errors()->add('close_out_dpa', 'Designated Person Ashore is filled out, please select a Close Out option.');
                }
                if ($hasCloseOutDate) {
                    $validator->errors()->add('close_out_date', 'Close Out Date is filled out, please select a Close Out option.');
                }
            }
            if ($hasCloseOutDate && $data['close_out_date'] > $today) {
                $validator->errors()->add('close_out_date', 'Close Out Date should not be greater than today.');
            }
        });
    }
}
