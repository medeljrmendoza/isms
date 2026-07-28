<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InternalAuditReport extends Model
{
    protected $fillable = [
        'audit_ref',
        'vessel_id',
        'this_date',
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

    /** Loose string-key relation — see AuditReport::nonconformities(). */
    public function nonconformities(): HasMany
    {
        return $this->hasMany(Nonconformity::class, 'source_of_nc_ref_no', 'audit_ref');
    }
}
