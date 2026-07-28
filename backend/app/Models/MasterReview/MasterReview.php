<?php

namespace App\Models\MasterReview;

use App\Models\ManualPublish\ManualDocument;
use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MasterReview extends Model
{
    protected $fillable = [
        'vessel_id',
        'manual_document_id',
        'manual_section',
        'review_date',
        'added_by',
        'review_quarter',
        'review_year',
        'is_deleted',
        'is_vessel_approved',
        'shore_status',
    ];

    protected function casts(): array
    {
        return [
            'review_date' => 'date',
            'is_deleted' => 'boolean',
            'is_vessel_approved' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }

    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(ManualDocument::class);
    }
}
