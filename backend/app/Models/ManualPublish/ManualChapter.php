<?php

namespace App\Models\ManualPublish;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ManualChapter extends Model
{
    protected $fillable = ['reference_no', 'chapter_name'];

    public function manualDocuments(): HasMany
    {
        return $this->hasMany(ManualDocument::class);
    }
}
