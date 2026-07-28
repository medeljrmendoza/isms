<?php

namespace App\Models\IncidentReports;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentRootCause extends Model
{
    protected $fillable = [
        'incident_report_id',
        'root_cause_id',
        'root_cause_other',
        'investigation',
        'analysis',
        'corrective_actions',
        'arrangement',
    ];

    public function incidentReport(): BelongsTo
    {
        return $this->belongsTo(IncidentReport::class);
    }

    public function rootCause(): BelongsTo
    {
        return $this->belongsTo(RootCause::class);
    }
}
