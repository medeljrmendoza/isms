<?php

namespace App\Models\VesselExports;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VesselExport extends Model
{
    protected $fillable = [
        'filename',
        'vessel_file',
        'vessel_id',
        'date_of_export',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'date_of_export' => 'date',
            'status' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
