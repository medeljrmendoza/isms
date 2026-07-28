<?php

namespace App\Models\Sire;

use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SireReport extends Model
{
    protected $fillable = [
        'vessel_id',
        'added_by',
        'dateof_inspection',
        'placeof_inspection',
        'company_name',
        'inspector_name',
        'sire_cost',
        'pass_fail',
        'shore_remarks',
        'vessel_remarks',
        'is_published',
        'is_approved',
        'is_deleted',
    ];

    protected function casts(): array
    {
        return [
            'dateof_inspection' => 'date',
            'sire_cost' => 'decimal:2',
            'is_published' => 'boolean',
            'is_approved' => 'boolean',
            'is_deleted' => 'boolean',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
