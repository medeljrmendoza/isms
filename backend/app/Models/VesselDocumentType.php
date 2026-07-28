<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VesselDocumentType extends Model
{
    protected $fillable = ['name', 'is_active', 'is_deleted'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vesselDocuments(): HasMany
    {
        return $this->hasMany(VesselDocument::class);
    }
}
