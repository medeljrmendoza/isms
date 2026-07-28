<?php

namespace App\Models\NonSire;

use App\Models\Vessel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NonSireReport extends Model
{
    protected $fillable = [
        'vessel_id',
        'added_by',
        'dateof_inspection',
        'placeof_inspection',
        'company_name',
        'inspector_name',
        'inspection_type',
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
