<?php

namespace App\Models\ManualPublish;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualDocument extends Model
{
    protected $fillable = [
        'manual_chapter_id',
        'reference_no',
        'manual_name',
        'date_of_revision',
        'is_published',
        'vessel_access',
        'file_hash',
    ];

    protected function casts(): array
    {
        return [
            'date_of_revision' => 'date',
            'is_published' => 'boolean',
        ];
    }

    public function manualChapter(): BelongsTo
    {
        return $this->belongsTo(ManualChapter::class);
    }

    public function vessels(): BelongsToMany
    {
        return $this->belongsToMany(Vessel::class, 'manual_document_vessel');
    }

    public function forms(): HasMany
    {
        return $this->hasMany(ManualForm::class);
    }
}
