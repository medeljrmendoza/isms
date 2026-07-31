<?php

namespace App\Models\IspsReview;

use App\Models\ManualPublish\ManualChapter;
use App\Models\ManualPublish\ManualDocument;
use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IspsReview extends Model
{
    protected $fillable = [
        'vessel_id',
        'manual_chapter_id',
        'manual_document_id',
        'manual_section',
        'review_date',
        'added_by',
        'review_quarter',
        'review_year',
        'review_description',
        'review_recommendation',
        'shore_reviewed_by',
        'shore_remarks',
        'vessel_reviewed_by',
        'vessel_reviewed_position',
        'vessel_remarks',
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

    public function manualChapter(): BelongsTo
    {
        return $this->belongsTo(ManualChapter::class);
    }

    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(ManualDocument::class);
    }

    public function present(): HasMany
    {
        return $this->hasMany(IspsReviewPresent::class)->orderBy('arrangement');
    }
}
