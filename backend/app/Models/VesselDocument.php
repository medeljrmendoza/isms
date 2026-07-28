<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VesselDocument extends Model
{
    protected $fillable = ['vessel_document_type_id', 'name', 'is_active', 'is_deleted'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vesselDocumentType(): BelongsTo
    {
        return $this->belongsTo(VesselDocumentType::class);
    }
}
