<?php

namespace App\Models\Claims;

use App\Models\Vessel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Claim extends Model
{
    protected $fillable = [
        'claim_no',
        'claims_category',
        'vessel_id',
        'report_date',
        'status',
        'nature_diagnosis',
        'amount_usd',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'amount_usd' => 'decimal:2',
        ];
    }

    public function vessel(): BelongsTo
    {
        return $this->belongsTo(Vessel::class);
    }
}
