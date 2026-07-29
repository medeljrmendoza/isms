<?php

namespace App\Models\Nonconformities;

use App\Models\CompanyInspections\AuditReport;
use App\Models\FlagState\FlagStateReport;
use App\Models\ManualPublish\ManualChapter;
use App\Models\PscReports\PscReport;
use App\Models\Vessel;
use App\Repositories\Nonconformities\NonconformityRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nonconformity extends Model
{
    /**
     * Source types whose approval lives on the source report itself
     * (Flag State, PSC, Company Inspection, Internal Audit, External
     * Audit) rather than being driven from this record — see
     * NonconformityRepository.
     */
    public const SOURCES_APPROVED_ELSEWHERE = [
        'FLAG STATE',
        'PSC INSPECTION',
        'COMPANY INSPECTION',
        'INTERNAL AUDIT',
        'EXTERNAL AUDIT',
    ];

    protected $fillable = [
        'ncr_no',
        'date_of_nc',
        'vessel_id',
        'company',
        'vessel_company',
        'department_name',
        'reported_by',
        'reporter_name',
        'description',
        'added_by',
        'source_of_nc',
        'source_of_nc_ref_no',
        'source_of_nc_others',
        'manual_chapter_id',
        'sms_details',
        'root_cause',
        'root_cause_incharge',
        'corrective_action',
        'corrective_action_incharge',
        'corrective_action_date',
        'verification',
        'verification_followup',
        'verification_assistance',
        'verification_dpa',
        'verification_date',
        'close_out_completed',
        'close_out_followup',
        'close_out_followup_nature',
        'close_out_dpa',
        'is_published',
        'is_approved',
        'is_inactive',
        'close_out_date',
        'attach_safety_meeting',
        'attach_record_training',
        'attach_logbook',
        'attach_delivery_note',
        'attach_photo',
        'attach_company_forms',
        'attach_others',
        'attach_others_details',
    ];

    protected function casts(): array
    {
        return [
            'date_of_nc' => 'date',
            'corrective_action_date' => 'date',
            'verification_date' => 'date',
            'close_out_date' => 'date',
            'is_published' => 'boolean',
            'is_approved' => 'boolean',
            'is_inactive' => 'boolean',
            'close_out_completed' => 'boolean',
            'close_out_followup' => 'boolean',
            'attach_safety_meeting' => 'boolean',
            'attach_record_training' => 'boolean',
            'attach_logbook' => 'boolean',
            'attach_delivery_note' => 'boolean',
            'attach_photo' => 'boolean',
            'attach_company_forms' => 'boolean',
            'attach_others' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    /** Loose string-key relation, the inverse of PscReport::nonconformities(). */
    public function pscReport(): BelongsTo
    {
        return $this->belongsTo(PscReport::class, 'source_of_nc_ref_no', 'ref_no');
    }

    /** Loose string-key relation, the inverse of FlagStateReport::nonconformities(). */
    public function flagStateReport(): BelongsTo
    {
        return $this->belongsTo(FlagStateReport::class, 'source_of_nc_ref_no', 'ref_no');
    }

    /** Loose string-key relation, the inverse of AuditReport::nonconformities(). */
    public function auditReport(): BelongsTo
    {
        return $this->belongsTo(AuditReport::class, 'source_of_nc_ref_no', 'audit_ref');
    }

    public function manualChapter(): BelongsTo
    {
        return $this->belongsTo(ManualChapter::class);
    }

    /**
     * Legacy stores this as a separate 'status' column set explicitly at
     * save time ('1' when close_out_date is filled, '0' otherwise). We
     * derive it instead of storing a second source of truth for the same fact.
     */
    public function getIsClosedAttribute(): bool
    {
        return $this->close_out_date !== null;
    }

    /** Whether this source type is exempt from this record's own publish/approve workflow. */
    public function getApprovedElsewhereAttribute(): bool
    {
        return in_array($this->source_of_nc, self::SOURCES_APPROVED_ELSEWHERE, true);
    }
}
