<?php

namespace App\Models\Pms;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PmsAdhocInventory extends Model
{
    protected $table = 'pms_adhoc_inventory';

    protected $fillable = ['pms_adhoc_id', 'pms_part_id', 'new_qty', 'reconditioned_qty'];

    public function adhoc(): BelongsTo
    {
        return $this->belongsTo(PmsAdhoc::class, 'pms_adhoc_id');
    }

    public function part(): BelongsTo
    {
        return $this->belongsTo(PmsPart::class, 'pms_part_id');
    }
}
