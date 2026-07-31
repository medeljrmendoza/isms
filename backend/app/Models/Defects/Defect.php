<?php

namespace App\Models\Defects;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Defect extends Model
{
    protected $fillable = [
        'vessel_id',
        'sl_no',
        'defect_date',
        'priority',
        'category',
        'compl_code',
        'description',
        'present_status',
        'raised_by',
        'expected_compl_date',
        'compl_date',
        'vessel_remarks',
        'shore_remarks',
    ];

    protected function casts(): array
    {
        return [
            'defect_date' => 'date',
            'expected_compl_date' => 'date',
            'compl_date' => 'date',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
