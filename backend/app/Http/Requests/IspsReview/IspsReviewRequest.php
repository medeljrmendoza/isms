<?php

namespace App\Http\Requests\IspsReview;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

/**
 * Ported from add_isps_review.php's bootstrapValidator (quarter/year/
 * description/recommendation notEmpty) plus its submitHandler (date
 * required and not in the future, year not in the future, Manual/chapter
 * required, Reviewed By required — legacy's own Procedure/Section
 * requirements are not enforced there either). shore_reviewed_by is free
 * text — legacy sources it from an Address Book "office personnel"
 * category that isn't part of this migration. There's no Vessel field:
 * legacy's Add form hides it entirely for SHORE records, and every
 * record created here is SHORE-added (see IspsReviewRepository::create()).
 */
class IspsReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'manual_chapter_id' => ['required', 'exists:manual_chapters,id'],
            'manual_document_id' => ['nullable', 'exists:manual_documents,id'],
            'manual_section' => ['nullable', 'string', 'max:255'],
            'review_date' => ['required', 'date'],
            'review_quarter' => ['required', 'integer', 'min:1', 'max:4'],
            'review_year' => ['required', 'digits:4', 'integer'],
            'review_description' => ['required', 'string'],
            'review_recommendation' => ['required', 'string'],
            'shore_reviewed_by' => ['required', 'string', 'max:255'],
            'shore_remarks' => ['nullable', 'string'],

            'present' => ['array'],
            'present.*.name' => ['required', 'string', 'max:255'],
            'present.*.position' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today();

            if (! empty($data['review_date']) && $data['review_date'] > $today->toDateString()) {
                $validator->errors()->add('review_date', 'Date should not be greater than today.');
            }

            if (! empty($data['review_year']) && (int) $data['review_year'] > (int) $today->format('Y')) {
                $validator->errors()->add('review_year', 'Year should not be greater than the current year.');
            }
        });
    }
}
