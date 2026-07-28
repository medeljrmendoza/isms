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
    ];

    protected function casts(): array
    {
        return [
            'defect_date' => 'date',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
