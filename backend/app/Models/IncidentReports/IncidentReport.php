<?php

namespace App\Models\IncidentReports;

use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IncidentReport extends Model
{
    protected $fillable = [
        'vessel_id',
        'voyage_no',
        'dateof_report',
        'report_no',
        'master_name',
        'chief_engineer_name',
        'person_reporting',
        'added_by',
        'published',
        'nature_type',
        'nature_of_incident_id',
        'hazardous_occurrence_type',
        'others',
        'accident_collision',
        'statementof_work',
        'bac',
        'vdr',
        'dateof_event',
        'timeof_event',
        'zone',
        'country',
        'speed',
        'course',
        'draft_forward',
        'draft_alt',
        'wind_direction',
        'direction_sea',
        'direction_swell',
        'geographical_location',
        'port_departure',
        'date_departure',
        'port_which_bound',
        'type_cargo',
        'cargo_quantity',
        'special_requirement',
        'atmospheric_clear',
        'atmospheric_partly_cloudy',
        'atmospheric_overcast',
        'atmospheric_fog',
        'atmospheric_rain',
        'atmospheric_snow',
        'atmospheric_other',
        'atmospheric_other_name',
        'distance1',
        'distance2',
        'distance3',
        'sea1',
        'sea2',
        'sea3',
        'crew_onboard',
        'other_onboard',
        'total_onboard',
        'crew_dead',
        'other_dead',
        'total_dead',
        'crew_missing',
        'other_missing',
        'total_missing',
        'crew_injured',
        'other_injured',
        'total_injured',
        'fs_ro',
        'incident_location_id',
        'location_other',
        'ship_position',
        'incident_operation_id',
        'ship_operation_other',
        'hazardous_occurrence_ppe_used',
        'hazardous_occurrence_ppe_used_comment',
        'hazardous_occurrence_severity',
        'hazardous_occurrence_severity_comment',
        'hazardous_occurrence_likelihood',
        'hazardous_occurrence_likelihood_comment',
        'subject_investigation',
        'evidence_safety_meeting',
        'evidence_certificate',
        'evidence_logbook',
        'evidence_delivery',
        'evidence_photo',
        'evidence_company',
        'evidence_others',
        'evidence_others_name',
        'causal_factor',
        'intermediate_cause',
        'shore_root_cause_summary',
        'severity_itp',
        'comment_itp',
        'location_of_injury_id',
        'type_of_injury_id',
        'other_typeof_injury',
        'other_affected_area',
        'severity_itv',
        'comment_itv',
        'signed_by',
        'date_signed',
        'vessel_remarks',
        'date_received',
        'reviewed_by',
        'investigator',
        'dpa',
        'closing_date',
        'is_approved',
    ];

    protected function casts(): array
    {
        return [
            'dateof_report' => 'date',
            'closing_date' => 'date',
            'dateof_event' => 'date',
            'date_departure' => 'date',
            'date_signed' => 'date',
            'date_received' => 'date',
            'is_approved' => 'boolean',
            'published' => 'boolean',
            'atmospheric_clear' => 'boolean',
            'atmospheric_partly_cloudy' => 'boolean',
            'atmospheric_overcast' => 'boolean',
            'atmospheric_fog' => 'boolean',
            'atmospheric_rain' => 'boolean',
            'atmospheric_snow' => 'boolean',
            'atmospheric_other' => 'boolean',
            'distance1' => 'boolean',
            'distance2' => 'boolean',
            'distance3' => 'boolean',
            'sea1' => 'boolean',
            'sea2' => 'boolean',
            'sea3' => 'boolean',
            'evidence_safety_meeting' => 'boolean',
            'evidence_certificate' => 'boolean',
            'evidence_logbook' => 'boolean',
            'evidence_delivery' => 'boolean',
            'evidence_photo' => 'boolean',
            'evidence_company' => 'boolean',
            'evidence_others' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function natureOfIncident(): BelongsTo
    {
        return $this->belongsTo(NatureOfIncident::class);
    }

    public function incidentLocation(): BelongsTo
    {
        return $this->belongsTo(IncidentLocation::class);
    }

    public function incidentOperation(): BelongsTo
    {
        return $this->belongsTo(IncidentOperation::class);
    }

    public function locationOfInjury(): BelongsTo
    {
        return $this->belongsTo(LocationOfInjury::class);
    }

    public function typeOfInjury(): BelongsTo
    {
        return $this->belongsTo(TypeOfInjury::class);
    }

    public function rootCauses(): HasMany
    {
        return $this->hasMany(IncidentRootCause::class)->orderBy('arrangement');
    }

    public function personsParticipated(): HasMany
    {
        return $this->hasMany(IncidentPersonParticipated::class)->orderBy('arrangement');
    }

    public function getIsClosedAttribute(): bool
    {
        return $this->closing_date !== null;
    }
}
