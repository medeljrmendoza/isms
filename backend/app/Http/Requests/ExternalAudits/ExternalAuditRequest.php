<?php

namespace App\Http\Requests\ExternalAudits;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/externalaudit/add_external.php's bootstrapValidator
 * config (ref_no, portof_audit required) plus its submitHandler checks
 * (vessel required on create, date of audit required and not in the
 * future). vessel_remarks is deliberately excluded — legacy renders
 * that field `disabled` in this admin form; it's only ever written by
 * the unmigrated vessel-side app.
 */
class ExternalAuditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('externalAuditReport')?->id;

        return [
            'ref_no' => [
                'required', 'string', 'max:255',
                Rule::unique('external_audit_reports', 'ref_no')->where(fn ($q) => $q->where('is_deleted', false))->ignore($ignoreId),
            ],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'department' => ['nullable', 'string', 'max:255'],
            'dateof_audit' => ['required', 'date'],
            'portof_audit' => ['required', 'string', 'max:255'],
            'typeof_audit' => ['nullable', 'in:ISM,ISPS,MLC,ISM/ISPS/MLC'],
            'master_name' => ['nullable', 'string', 'max:255'],
            'chief_engineer' => ['nullable', 'string', 'max:255'],
            'auditor_name' => ['nullable', 'string', 'max:255'],
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

            if (($data['dateof_audit'] ?? null) > $today) {
                $validator->errors()->add('dateof_audit', 'Date of Audit should not be greater than today.');
            }
        });
    }
}
