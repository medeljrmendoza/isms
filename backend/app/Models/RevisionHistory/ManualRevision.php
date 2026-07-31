<?php

namespace App\Models\RevisionHistory;

use App\Models\ManualPublish\ManualDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualRevision extends Model
{
    protected $fillable = [
        'manual_document_id',
        'arrangement',
        'revision_no',
        'date_revised',
        'section',
        'reason_revision',
        'reviewed_by',
        'approved_by',
    ];

    protected function casts(): array
    {
        return [
            'date_revised' => 'date',
        ];
    }

    public function manualDocument(): BelongsTo
    {
        return $this->belongsTo(ManualDocument::class);
    }
}
