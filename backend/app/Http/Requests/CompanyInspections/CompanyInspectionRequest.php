<?php

namespace App\Http\Requests\CompanyInspections;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/companyinspection/add_company_report.php's
 * bootstrapValidator config (audit_ref, vessel_company and
 * placeof_audit required) plus its submitHandler checks (date of
 * inspection required and not in the future; vessel required when
 * attributing to a VESSEL — create only; company name required when
 * attributing to the COMPANY).
 */
class CompanyInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ignoreId = $this->route('auditReport')?->id;

        return [
            'audit_ref' => [
                'required', 'string', 'max:255',
                Rule::unique('audit_reports', 'audit_ref')->where(fn ($q) => $q->where('is_deleted', false))->ignore($ignoreId),
            ],
            'vessel_company' => ['required', 'in:VESSEL,COMPANY'],
            'vessel_id' => ['nullable', 'exists:vessels,id'],
            'company' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'this_date' => ['required', 'date'],
            'placeof_audit' => ['required', 'string', 'max:255'],
            'audit_type_id' => ['nullable', 'exists:audit_types,id'],
            'audit_kind_id' => ['nullable', 'exists:audit_kinds,id'],
            'inspector_name' => ['nullable', 'string', 'max:255'],
            'master_name' => ['nullable', 'string', 'max:255'],
            'chief_engineer' => ['nullable', 'string', 'max:255'],
            'remarks' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today()->toDateString();
            $existing = $this->route('auditReport');

            // Attribution is frozen after creation (the repository ignores
            // both fields on update), so the stored value — not the
            // payload — decides which conditional rule applies.
            $vesselCompany = $existing?->vessel_company ?? ($data['vessel_company'] ?? null);

            if (($data['this_date'] ?? null) > $today) {
                $validator->errors()->add('this_date', 'Date of Inspection should not be greater than today.');
            }

            // Legacy only enforces the vessel selection on the insert
            // branch, since edits can't change it anyway.
            if ($existing === null && $vesselCompany === 'VESSEL' && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            // The company name stays editable on both branches — legacy
            // deliberately re-reads it from the edit payload.
            if ($vesselCompany === 'COMPANY' && empty($data['company'])) {
                $validator->errors()->add('company', 'Please input the company.');
            }
        });
    }
}
