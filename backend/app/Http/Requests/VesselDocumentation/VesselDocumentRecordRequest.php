<?php

namespace App\Http\Requests\VesselDocumentation;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from add_vessel_documentation_v.php's bootstrapValidator config +
 * submitHandler: only Date Issued is genuinely required by the form itself
 * (`date_issued: notEmpty`); Vessel and Document are required on create
 * only, checked in the submitHandler's own `if ($("#vessel_docID").val()
 * == "")` branch, same pattern as every other report module's
 * create-only vessel_id check. vessel_document_id uniqueness per vessel
 * (scoped to non-deleted rows) enforces the same "one live record per
 * catalog document" invariant the Add form's document-picker relies on
 * (see VesselDocumentationRepository::catalogOptionsForVessel).
 */
class VesselDocumentRecordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('vesselDocumentRecord')?->id;

        return [
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'vessel_document_id' => [
                'nullable', 'exists:vessel_documents,id',
                Rule::unique('vessel_document_records', 'vessel_document_id')
                    ->where(fn ($q) => $q->where('vessel_id', $this->input('vessel_id'))->where('is_deleted', false))
                    ->ignore($ignoreId),
            ],
            'doc_number' => ['nullable', 'string', 'max:255'],
            'issuing_body' => ['nullable', 'string', 'max:255'],
            'date_issued' => ['required', 'date'],
            'date_expired' => ['nullable', 'date'],
            'date_range_from' => ['nullable', 'date'],
            'date_range_to' => ['nullable', 'date', 'after_or_equal:date_range_from'],
            'is_printer_friendly' => ['sometimes', 'boolean'],
            'shore_remarks' => ['nullable', 'string'],
            'vessel_remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $isCreate = $this->isMethod('post');

            if ($isCreate && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            if ($isCreate && empty($data['vessel_document_id'])) {
                $validator->errors()->add('vessel_document_id', 'Please select a document.');
            }
        });
    }
}
