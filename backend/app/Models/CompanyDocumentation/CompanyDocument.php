<?php

namespace App\Models\CompanyDocumentation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompanyDocument extends Model
{
    protected $fillable = ['company_document_type_id', 'name', 'is_active', 'is_deleted'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function companyDocumentType(): BelongsTo
    {
        return $this->belongsTo(CompanyDocumentType::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(CompanyDocumentationRecord::class);
    }
}
