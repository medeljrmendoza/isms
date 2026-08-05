<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsSubClassification extends Model
{
    protected $fillable = ['pms_classification_id', 'chart_code', 'name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function classification(): BelongsTo
    {
        return $this->belongsTo(PmsClassification::class, 'pms_classification_id');
    }
}
