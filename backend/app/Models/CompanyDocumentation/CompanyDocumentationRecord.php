<?php

namespace App\Models\CompanyDocumentation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyDocumentationRecord extends Model
{
    protected $fillable = [
        'company_document_id',
        'date_issued',
        'date_expired',
        'date_range_from',
        'date_range_to',
        'is_active',
        'is_deleted',
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

    public function companyDocument(): BelongsTo
    {
        return $this->belongsTo(CompanyDocument::class);
    }
}
