<?php

namespace App\Models\CompanyInspections;

use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AuditReport extends Model
{
    protected $fillable = [
        'audit_ref',
        'vessel_id',
        'company',
        'vessel_company',
        'department',
        'this_date',
        'placeof_audit',
        'audit_type_id',
        'audit_kind_id',
        'inspector_name',
        'master_name',
        'chief_engineer',
        'remarks',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'this_date' => 'date',
            'is_deleted' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function auditType(): BelongsTo
    {
        return $this->belongsTo(AuditType::class);
    }

    public function auditKind(): BelongsTo
    {
        return $this->belongsTo(AuditKind::class);
    }

    /**
     * Not a real FK — legacy links these by matching
     * tb_nonconformities.source_of_nc_ref_no against this report's own
     * audit_ref string, so the relation is defined the same way.
     */
    public function nonconformities(): HasMany
    {
        return $this->hasMany(Nonconformity::class, 'source_of_nc_ref_no', 'audit_ref');
    }
}
