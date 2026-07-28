<?php

namespace App\Models\PscReports;

use App\Models\CompanyInspections\AuditReport;
use App\Models\Nonconformities\Nonconformity;
use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PscReport extends Model
{
    protected $fillable = [
        'ref_no',
        'vessel_id',
        'dateof_inspection',
        'placeof_inspection',
        'mou_id',
        'mou_others',
        'name_psco',
        'master_name',
        'chief_engineer',
        'is_detained',
        'detained_date',
        'detained_time',
        'is_released',
        'released_date',
        'released_time',
        'closing_date',
        'remarks',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'dateof_inspection' => 'date',
            'detained_date' => 'date',
            'released_date' => 'date',
            'closing_date' => 'date',
            'is_detained' => 'boolean',
            'is_released' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function mou(): BelongsTo
    {
        return $this->belongsTo(PscMouAuthority::class, 'mou_id');
    }

    /** Loose string-key relation — see AuditReport::nonconformities(). */
    public function nonconformities(): HasMany
    {
        return $this->hasMany(Nonconformity::class, 'source_of_nc_ref_no', 'ref_no');
    }
}
