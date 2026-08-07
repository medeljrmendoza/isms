<?php

namespace App\Http\Requests\IncidentReports;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Ported from admin/incident/add_incident_report_v.php's bootstrapValidator
 * config and submitHandler checks, adapted to legacy varchar lookup IDs
 * (tb_natureof_incident, tb_incident_location_occurences,
 * tb_incident_operations, tb_typeof_injury, tb_locationof_injuries,
 * tb_root_cause) instead of local numeric FKs. A few of legacy's "always
 * required" fields (severity_itp, injuryto_people_type/location,
 * injuryto_vessel_severity, ship_position) are deliberately NOT made
 * unconditionally required here — legacy's own bootstrapValidator `fields`
 * block doesn't scope them to the conditionally-shown sections they belong
 * to, which would make it impossible to submit a "no injury occurred" or a
 * pure-accident report. They're validated conditionally instead, consistent
 * with how every other "OTHER — please specify" pair in this form already
 * works.
 */
class IncidentReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'vessel_id' => ['nullable', 'string'],
            'voyage_no' => ['nullable', 'string', 'max:255'],
            'dateof_report' => ['required', 'date'],
            'report_no' => ['nullable', 'string', 'max:255'],
            'master_name' => ['nullable', 'string', 'max:255'],
            'chief_engineer_name' => ['nullable', 'string', 'max:255'],
            'person_reporting' => ['nullable', 'string', 'max:255'],
            'nature_type' => ['required', 'in:accident,hazardous_occurrence'],
            'statementof_work' => ['required', 'string'],

            'nature_of_incident_id' => ['nullable', 'string'],
            'accident_collision' => ['nullable', 'string', 'max:255'],
            'others' => ['nullable', 'string', 'max:255'],
            'bac' => ['nullable', 'in:NO,YES'],
            'vdr' => ['nullable', 'in:NO,YES'],
            'dateof_event' => ['nullable', 'date'],
            'timeof_event' => ['nullable', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'speed' => ['nullable', 'string', 'max:255'],
            'course' => ['nullable', 'string', 'max:255'],
            'draft_forward' => ['nullable', 'string', 'max:255'],
            'draft_alt' => ['nullable', 'string', 'max:255'],
            'wind_direction' => ['nullable', 'string', 'max:255'],
            'direction_sea' => ['nullable', 'string', 'max:255'],
            'direction_swell' => ['nullable', 'string', 'max:255'],
            'geographical_location' => ['nullable', 'string', 'max:255'],
            'port_departure' => ['nullable', 'string', 'max:255'],
            'date_departure' => ['nullable', 'date'],
            'port_which_bound' => ['nullable', 'string', 'max:255'],
            'type_cargo' => ['nullable', 'string', 'max:255'],
            'cargo_quantity' => ['nullable', 'string', 'max:255'],
            'special_requirement' => ['nullable', 'string', 'max:255'],
            'atmospheric_clear' => ['boolean'],
            'atmospheric_partly_cloudy' => ['boolean'],
            'atmospheric_overcast' => ['boolean'],
            'atmospheric_fog' => ['boolean'],
            'atmospheric_rain' => ['boolean'],
            'atmospheric_snow' => ['boolean'],
            'atmospheric_other' => ['boolean'],
            'atmospheric_other_name' => ['nullable', 'string', 'max:255'],
            'distance1' => ['boolean'],
            'distance2' => ['boolean'],
            'distance3' => ['boolean'],
            'sea1' => ['boolean'],
            'sea2' => ['boolean'],
            'sea3' => ['boolean'],
            'crew_onboard' => ['nullable', 'integer', 'min:0'],
            'other_onboard' => ['nullable', 'integer', 'min:0'],
            'total_onboard' => ['nullable', 'integer', 'min:0'],
            'crew_dead' => ['nullable', 'integer', 'min:0'],
            'other_dead' => ['nullable', 'integer', 'min:0'],
            'total_dead' => ['nullable', 'integer', 'min:0'],
            'crew_missing' => ['nullable', 'integer', 'min:0'],
            'other_missing' => ['nullable', 'integer', 'min:0'],
            'total_missing' => ['nullable', 'integer', 'min:0'],
            'crew_injured' => ['nullable', 'integer', 'min:0'],
            'other_injured' => ['nullable', 'integer', 'min:0'],
            'total_injured' => ['nullable', 'integer', 'min:0'],
            'fs_ro' => ['nullable', 'in:NO,YES'],

            'hazardous_occurrence_type' => ['nullable', 'in:unsafe_act,unsafe_condition,near_miss'],
            'incident_location_id' => ['nullable', 'string'],
            'location_other' => ['nullable', 'string', 'max:255'],
            'ship_position' => ['nullable', 'string', 'max:255'],
            'incident_operation_id' => ['nullable', 'string'],
            'ship_operation_other' => ['nullable', 'string', 'max:255'],
            'hazardous_occurrence_ppe_used' => ['nullable', 'in:NO,YES,NA'],
            'hazardous_occurrence_ppe_used_comment' => ['nullable', 'string'],
            'hazardous_occurrence_severity' => ['nullable', 'in:HIGH,MEDIUM,LOW'],
            'hazardous_occurrence_severity_comment' => ['nullable', 'string'],
            'hazardous_occurrence_likelihood' => ['nullable', 'in:HIGH,MEDIUM,LOW'],
            'hazardous_occurrence_likelihood_comment' => ['nullable', 'string'],
            'subject_investigation' => ['nullable', 'in:NO,YES'],
            'evidence_safety_meeting' => ['boolean'],
            'evidence_certificate' => ['boolean'],
            'evidence_logbook' => ['boolean'],
            'evidence_delivery' => ['boolean'],
            'evidence_photo' => ['boolean'],
            'evidence_company' => ['boolean'],
            'evidence_others' => ['boolean'],
            'evidence_others_name' => ['nullable', 'string', 'max:255'],
            'causal_factor' => ['nullable', 'string'],
            'intermediate_cause' => ['nullable', 'string'],
            'shore_root_cause_summary' => ['nullable', 'string'],

            'severity_itp' => ['nullable', 'in:FATALITY,FAC,LWC,MTC,PPD,PTD,RWC'],
            'comment_itp' => ['nullable', 'string'],
            'location_of_injury_id' => ['nullable', 'string'],
            'type_of_injury_id' => ['nullable', 'string'],
            'other_typeof_injury' => ['nullable', 'string', 'max:255'],
            'other_affected_area' => ['nullable', 'string', 'max:255'],
            'severity_itv' => ['nullable', 'in:low,medium,high'],
            'comment_itv' => ['nullable', 'string'],

            'root_causes' => ['array'],
            'root_causes.*.root_cause_id' => ['nullable', 'string'],
            'root_causes.*.root_cause_other' => ['nullable', 'string', 'max:255'],
            'root_causes.*.investigation' => ['required', 'string'],
            'root_causes.*.analysis' => ['required', 'string'],
            'root_causes.*.corrective_actions' => ['required', 'string'],

            'persons' => ['array'],
            'persons.*.person_name' => ['required', 'string', 'max:255'],
            'persons.*.position' => ['required', 'string', 'max:255'],

            'signed_by' => ['required', 'string', 'max:255'],
            'date_signed' => ['required', 'date'],
            'vessel_remarks' => ['nullable', 'string'],
            'date_received' => ['required', 'date'],
            'reviewed_by' => ['nullable', 'string', 'max:255'],
            'investigator' => ['nullable', 'string', 'max:255'],
            'dpa' => ['required', 'string', 'max:255'],
            'closing_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $data = $validator->getData();
            $today = Carbon::today()->toDateString();
            $isCreate = $this->isMethod('post');
            $isAccident = ($data['nature_type'] ?? null) === 'accident';
            $isHazardous = ($data['nature_type'] ?? null) === 'hazardous_occurrence';
            $legacy = DB::connection('legacy');

            if ($isCreate && empty($data['vessel_id'])) {
                $validator->errors()->add('vessel_id', 'Please select a vessel.');
            }

            if (($data['dateof_report'] ?? null) > $today) {
                $validator->errors()->add('dateof_report', 'Date of Report should not be greater than today.');
            }

            if ($isAccident) {
                if (empty($data['nature_of_incident_id'])) {
                    $validator->errors()->add('nature_of_incident_id', 'Please select the nature of incident.');
                } else {
                    $name = $legacy->table('tb_natureof_incident')->where('natureID', $data['nature_of_incident_id'])->value('name');
                    if ($name === 'Other' && empty($data['others'])) {
                        $validator->errors()->add('others', 'Please specify the other nature of incident.');
                    }
                    if ($name === 'Collision' && empty($data['accident_collision'])) {
                        $validator->errors()->add('accident_collision', 'Please specify the other vessel(s) details.');
                    }
                }

                if (empty($data['dateof_event'])) {
                    $validator->errors()->add('dateof_event', 'Please input Date of Event.');
                } elseif ($data['dateof_event'] > $today) {
                    $validator->errors()->add('dateof_event', 'Date of Event should not be greater than today.');
                }

                if (! empty($data['date_departure']) && $data['date_departure'] > $today) {
                    $validator->errors()->add('date_departure', 'Date of Departure should not be greater than today.');
                }

                if (! empty($data['atmospheric_other']) && empty($data['atmospheric_other_name'])) {
                    $validator->errors()->add('atmospheric_other_name', 'Please specify the other atmospheric condition.');
                }
            }

            if ($isHazardous) {
                if (empty($data['hazardous_occurrence_type'])) {
                    $validator->errors()->add('hazardous_occurrence_type', 'Please select the type of hazardous occurrence.');
                }
                if (empty($data['hazardous_occurrence_severity'])) {
                    $validator->errors()->add('hazardous_occurrence_severity', 'Please select the severity.');
                }
                if (empty($data['hazardous_occurrence_likelihood'])) {
                    $validator->errors()->add('hazardous_occurrence_likelihood', 'Please select the likelihood.');
                }

                if (empty($data['incident_location_id'])) {
                    $validator->errors()->add('incident_location_id', 'Please select the location.');
                } else {
                    $name = $legacy->table('tb_incident_location_occurences')->where('incidentLocationID', $data['incident_location_id'])->value('location_occurences');
                    if ($name === 'OTHER' && empty($data['location_other'])) {
                        $validator->errors()->add('location_other', 'Please specify the other location.');
                    }
                }

                if (empty($data['incident_operation_id'])) {
                    $validator->errors()->add('incident_operation_id', 'Please select the ship\'s operation.');
                } else {
                    $name = $legacy->table('tb_incident_operations')->where('incidentOperationID', $data['incident_operation_id'])->value('operation_name');
                    if ($name === 'OTHER' && empty($data['ship_operation_other'])) {
                        $validator->errors()->add('ship_operation_other', 'Please specify the other ship\'s operation.');
                    }
                }

                if (! empty($data['evidence_others']) && empty($data['evidence_others_name'])) {
                    $validator->errors()->add('evidence_others_name', 'Please specify the other required evidence.');
                }
            }

            if (! empty($data['severity_itp'])) {
                if (empty($data['type_of_injury_id'])) {
                    $validator->errors()->add('type_of_injury_id', 'Please select the type of injury.');
                } else {
                    $name = $legacy->table('tb_typeof_injury')->where('type_ID', $data['type_of_injury_id'])->value('type');
                    if ($name === 'Other' && empty($data['other_typeof_injury'])) {
                        $validator->errors()->add('other_typeof_injury', 'Please specify the other type of injury.');
                    }
                }

                if (empty($data['location_of_injury_id'])) {
                    $validator->errors()->add('location_of_injury_id', 'Please select the affected area.');
                } else {
                    $bodyPart = $legacy->table('tb_locationof_injuries')->where('locationID', $data['location_of_injury_id'])->value('body_part');
                    if ($bodyPart === 'Other' && empty($data['other_affected_area'])) {
                        $validator->errors()->add('other_affected_area', 'Please specify the other affected area.');
                    }
                }
            }

            foreach ($data['root_causes'] ?? [] as $index => $row) {
                if (! empty($row['root_cause_id'])) {
                    $category = $legacy->table('tb_root_cause')
                        ->leftJoin('tb_root_cause_category', 'tb_root_cause_category.rootCauseCatID', '=', 'tb_root_cause.rootCauseCatID')
                        ->where('tb_root_cause.rootCauseID', $row['root_cause_id'])
                        ->value('tb_root_cause_category.category');
                    if ($category === 'OTHER' && empty($row['root_cause_other'])) {
                        $validator->errors()->add("root_causes.{$index}.root_cause_other", 'Please complete details for this root cause.');
                    }
                }
            }

            if (($data['date_signed'] ?? null) > $today) {
                $validator->errors()->add('date_signed', 'Date Signed should not be greater than today.');
            }
            if (($data['date_received'] ?? null) > $today) {
                $validator->errors()->add('date_received', 'Date Received should not be greater than today.');
            }
            if (! empty($data['closing_date']) && $data['closing_date'] > $today) {
                $validator->errors()->add('closing_date', 'Closing Date should not be greater than today.');
            }
        });
    }

    /** @return array<string, mixed> */
    public function validated($key = null, $default = null): array
    {
        $data = parent::validated($key, $default);

        $booleanFields = [
            'atmospheric_clear', 'atmospheric_partly_cloudy', 'atmospheric_overcast', 'atmospheric_fog',
            'atmospheric_rain', 'atmospheric_snow', 'atmospheric_other', 'distance1', 'distance2', 'distance3',
            'sea1', 'sea2', 'sea3', 'evidence_safety_meeting', 'evidence_certificate', 'evidence_logbook',
            'evidence_delivery', 'evidence_photo', 'evidence_company', 'evidence_others',
        ];

        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = $data[$field] ? '1' : '0';
            }
        }

        return $data;
    }
}
