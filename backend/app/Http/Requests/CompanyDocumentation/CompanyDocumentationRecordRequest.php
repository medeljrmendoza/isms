<?php

namespace App\Http\Requests\CompanyDocumentation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Ported from add_company_documentation_v.php's bootstrapValidator +
 * submitHandler: only Date Issued is `notEmpty`; Document is required
 * on create only (frozen after, same convention as
 * VesselDocumentRecordRequest). Page No.'s odd PF-linked validation is
 * dropped along with the field itself — see the add_full_record_fields
 * migration's docblock.
 */
class CompanyDocumentationRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'company_document_id' => ['nullable', 'exists:company_documents,id'],
            'doc_number' => ['nullable', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'date_issued' => ['required', 'date'],
            'date_expired' => ['nullable', 'date'],
            'date_range_from' => ['nullable', 'date'],
            'date_range_to' => ['nullable', 'date', 'after_or_equal:date_range_from'],
            'is_printer_friendly' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $isCreate = $this->isMethod('post');

            if ($isCreate && empty($data['company_document_id'])) {
                $validator->errors()->add('company_document_id', 'Please select a document.');
            }
        });
    }
}
