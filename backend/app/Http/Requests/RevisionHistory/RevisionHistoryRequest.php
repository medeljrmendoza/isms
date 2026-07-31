<?php

namespace App\Http\Requests\RevisionHistory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Ported from add_sms_revision.php's bootstrapValidator (order/revision
 * no./date/reviewed by/approved by notEmpty — the "the_revision"
 * validator entry references a field that's commented out of the form
 * itself, so it's dead and reason_revision stays optional to match) plus
 * its submitHandler date bounds (must be after 1975-01-01, not after
 * today). Section has no required marker in the legacy form either.
 */
class RevisionHistoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manual_document_id' => ['required', 'exists:manual_documents,id'],
            'arrangement' => ['required', 'integer', 'min:1'],
            'revision_no' => ['required', 'string', 'max:255'],
            'date_revised' => ['required', 'date'],
            'section' => ['nullable', 'string', 'max:255'],
            'reason_revision' => ['nullable', 'string'],
            'reviewed_by' => ['required', 'string', 'max:255'],
            'approved_by' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();

            if (! empty($data['date_revised']) && $data['date_revised'] <= '1975-01-01') {
                $validator->errors()->add('date_revised', 'Please input a valid Date of Revision.');
            }

            if (! empty($data['date_revised']) && $data['date_revised'] > Carbon::today()->toDateString()) {
                $validator->errors()->add('date_revised', 'Date of Revision should not be greater than today.');
            }
        });
    }
}
