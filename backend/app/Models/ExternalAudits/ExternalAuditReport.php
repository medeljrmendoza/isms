<?php

namespace App\Models\ExternalAudits;

use App\Models\CompanyInspections\AuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalAuditReport extends Model
{
    protected $fillable = [
        'ref_no',
        'vessel_id',
        'dateof_audit',
        'added_by',
        'is_published',
        'is_approved',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'dateof_audit' => 'date',
            'is_published' => 'boolean',
            'is_approved' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    /** Loose string-key relation — see AuditReport::nonconformities(). */
    public function nonconformities(): HasMany
    {
        return $this->hasMany(Nonconformity::class, 'source_of_nc_ref_no', 'ref_no');
    }
}
