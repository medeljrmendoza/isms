<?php

namespace App\Http\Requests\Nonconformities;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from add_nonconformity.php's bootstrapValidator + the manual
 * pairing checks in its submitHandler (corrective action/date, DPA/date
 * pairs for Verification and Close Out must all be filled or all
 * empty). vessel_id/vessel_company are only meaningfully required on
 * create — the legacy edit form locks Vessel/Company and the controller
 * discards whatever it's submitted with, keeping the existing row's
 * value instead (see NonconformityRepository::legacySave()). source_of_nc
 * is restricted to OPERATIONAL/OTHERS on create, matching the two radios
 * the Add form actually renders; edit additionally allows the 5
 * auto-generated sources through, since the frontend re-submits a
 * locked value unchanged for those records rather than offering an
 * editable radio.
 */
class NonconformityRequest extends FormRequest
{
    private const SPECIAL_SOURCES = ['FLAG STATE', 'PSC INSPECTION', 'COMPANY INSPECTION', 'INTERNAL AUDIT', 'EXTERNAL AUDIT'];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isCreate = $this->isMethod('post');

        $rules = [
            'ncr_no' => ['required', 'string', 'max:255'],
            'date_of_nc' => ['required', 'date', 'before_or_equal:today'],
            'vessel_company' => ['required', Rule::in(['VESSEL', 'COMPANY'])],
            'company' => ['nullable', 'string', 'max:255'],
            'department_name' => ['nullable', 'string', 'max:255'],
            'reported_by' => ['required', Rule::in(['SHORE', 'VESSEL'])],
            'reporter_name' => ['nullable', 'string', 'max:255'],
            'source_of_nc' => ['required', Rule::in($isCreate ? ['OPERATIONAL', 'OTHERS'] : [...self::SPECIAL_SOURCES, 'OPERATIONAL', 'OTHERS'])],
            'source_of_nc_others' => ['required_if:source_of_nc,OTHERS', 'nullable', 'string', 'max:255'],
            'source_of_nc_ref_no' => ['nullable', 'string', 'max:255'],
            'sms_chapterID' => ['nullable', 'string'],
            'sms_details' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'root_cause' => ['nullable', 'string'],
            'root_cause_incharge' => ['nullable', 'string', 'max:255'],
            'corrective_action' => ['nullable', 'string'],
            'corrective_action_incharge' => ['nullable', 'string', 'max:255'],
            'corrective_action_date' => ['nullable', 'date'],
            'verification' => ['nullable', Rule::in(['COMPLETED', 'FOLLOW-UP', 'ASSISTANCE'])],
            'verification_followup' => ['nullable', 'string'],
            'verification_assistance' => ['nullable', 'string'],
            'verification_dpa' => ['nullable', 'string', 'max:255'],
            'verification_date' => ['nullable', 'date', 'before_or_equal:today'],
            'close_out_completed' => ['nullable', 'boolean'],
            'close_out_followup' => ['nullable', 'boolean'],
            'close_out_followup_nature' => ['nullable', 'string'],
            'close_out_dpa' => ['nullable', 'string', 'max:255'],
            'close_out_date' => ['nullable', 'date', 'before_or_equal:today'],
            'attach_safety_meeting' => ['nullable', 'boolean'],
            'attach_record_training' => ['nullable', 'boolean'],
            'attach_logbook' => ['nullable', 'boolean'],
            'attach_delivery_note' => ['nullable', 'boolean'],
            'attach_photo' => ['nullable', 'boolean'],
            'attach_company_forms' => ['nullable', 'boolean'],
            'attach_others' => ['nullable', 'boolean'],
            'attach_others_details' => ['nullable', 'string', 'max:255'],
        ];

        if ($isCreate) {
            $rules['vessel_id'] = ['required_if:vessel_company,VESSEL', 'nullable', 'string'];
        }
        $rules['company'][] = Rule::requiredIf($this->input('vessel_company') === 'COMPANY');

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $hasCorrectiveAction = trim((string) $this->input('corrective_action')) !== '';
            $hasCorrectiveDate = trim((string) $this->input('corrective_action_date')) !== '';
            if ($hasCorrectiveAction && ! $hasCorrectiveDate) {
                $validator->errors()->add('corrective_action_date', 'Proposed Corrective Action is filled out, please input Target Date of Completion.');
            }
            if ($hasCorrectiveDate && ! $hasCorrectiveAction) {
                $validator->errors()->add('corrective_action', 'Target Date of Completion is filled out, please input Proposed Corrective Action.');
            }
            if ($hasCorrectiveDate && $this->input('corrective_action_date') < $this->input('date_of_nc')) {
                $validator->errors()->add('corrective_action_date', 'Target Date of Completion should not be less than Date of NC.');
            }

            $hasVerification = trim((string) $this->input('verification')) !== '';
            $hasVerificationDpa = trim((string) $this->input('verification_dpa')) !== '';
            $hasVerificationDate = trim((string) $this->input('verification_date')) !== '';
            if ($hasVerification && (! $hasVerificationDpa || ! $hasVerificationDate)) {
                $validator->errors()->add('verification_dpa', 'Verification of Corrective Action has been selected, please input DPA and Verification Date.');
            }
            if (! $hasVerification && ($hasVerificationDpa || $hasVerificationDate)) {
                $validator->errors()->add('verification', 'DPA/Verification Date is filled out, please select Verification of Corrective Action.');
            }

            $hasCloseOut = $this->boolean('close_out_completed') || $this->boolean('close_out_followup');
            $hasCloseOutDpa = trim((string) $this->input('close_out_dpa')) !== '';
            $hasCloseOutDate = trim((string) $this->input('close_out_date')) !== '';
            if ($hasCloseOut && (! $hasCloseOutDpa || ! $hasCloseOutDate)) {
                $validator->errors()->add('close_out_dpa', 'Close Out has been selected, please input Designated Person Ashore and Close Out Date.');
            }
            if (! $hasCloseOut && ($hasCloseOutDpa || $hasCloseOutDate)) {
                $validator->errors()->add('close_out_completed', 'Designated Person Ashore/Close Out Date is filled out, please select on Close Out.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        foreach (['close_out_completed', 'close_out_followup', 'attach_safety_meeting', 'attach_record_training', 'attach_logbook', 'attach_delivery_note', 'attach_photo', 'attach_company_forms', 'attach_others'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $data[$flag] = $data[$flag] ? '1' : '0';
            }
        }

        return $data;
    }
}
