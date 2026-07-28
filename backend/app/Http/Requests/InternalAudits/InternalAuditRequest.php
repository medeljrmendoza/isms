<?php

namespace App\Http\Requests\InternalAudits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/internalaudit/add_internal_report.php's
 * bootstrapValidator config (audit_ref required + regex-restricted to
 * letters/numbers/hyphens/underscores, placeof_audit required) plus its
 * submitHandler checks (vessel required on create, date of audit
 * required and not in the future).
 */
class InternalAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('internalAuditReport')?->id;

        return [
            'audit_ref' => [
                'required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_-]+$/',
                Rule::unique('internal_audit_reports', 'audit_ref')->where(fn ($q) => $q->where('is_deleted', false))->ignore($ignoreId),
            ],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'this_date' => ['required', 'date'],
            'placeof_audit' => ['required', 'string', 'max:255'],
            'typeof_audit' => ['nullable', 'in:ISM,ISPS,MLC,ISM/ISPS/MLC'],
            'master_name' => ['nullable', 'string', 'max:255'],
            'chief_engineer' => ['nullable', 'string', 'max:255'],
            'auditor_name' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
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

            if (($data['this_date'] ?? null) > $today) {
                $validator->errors()->add('this_date', 'Date of Audit should not be greater than today.');
            }
        });
    }
}
