<?php

namespace App\Models\ManualPublish;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ManualForm extends Model
{
    protected $fillable = [
        'manual_document_id',
        'reference_no',
        'file_name',
        'is_active',
        'is_deleted',
        'vessel_access',
        'file_hash',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vessels(): BelongsToMany
    {
        return $this->belongsToMany(Vessel::class, 'manual_form_vessel');
    }

    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(ManualDocument::class);
    }
}
