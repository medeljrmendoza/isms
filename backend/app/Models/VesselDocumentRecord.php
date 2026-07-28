<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VesselDocumentRecord extends Model
{
    protected $fillable = [
        'vessel_id',
        'vessel_document_id',
        'date_issued',
        'date_expired',
        'date_range_from',
        'date_range_to',
        'is_active',
        'is_deleted',
        'vessel_file_hash',
        'shore_file_hash',
    ];

    protected function casts(): array
    {
        return [
            'date_issued' => 'date',
            'date_expired' => 'date',
            'date_range_from' => 'date',
            'date_range_to' => 'date',
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function vesselDocument(): BelongsTo
    {
        return $this->belongsTo(VesselDocument::class);
    }
}
