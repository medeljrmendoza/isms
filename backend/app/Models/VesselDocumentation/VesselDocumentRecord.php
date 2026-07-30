<?php

namespace App\Models\VesselDocumentation;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VesselDocumentRecord extends Model
{
    protected $fillable = [
        'vessel_id',
        'vessel_document_id',
        'doc_number',
        'issuing_body',
        'date_issued',
        'date_expired',
        'date_range_from',
        'date_range_to',
        'is_printer_friendly',
        'shore_remarks',
        'vessel_remarks',
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
            'is_printer_friendly' => 'boolean',
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
