<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IncidentPersonParticipated extends Model
{
    protected $table = 'incident_persons_participated';

    protected $fillable = ['incident_report_id', 'person_name', 'position', 'arrangement'];

    public function incidentReport(): BelongsTo
    {
        return $this->belongsTo(IncidentReport::class);
    }
}
