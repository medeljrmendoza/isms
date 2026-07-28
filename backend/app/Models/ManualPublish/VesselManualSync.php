<?php

namespace App\Models\ManualPublish;

use Illuminate\Database\Eloquent\Model;

class VesselManualSync extends Model
{
    protected $fillable = ['vessel_id', 'manual_document_id', 'file_hash'];
}
