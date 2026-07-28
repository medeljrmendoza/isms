<?php

namespace App\Models\ManualPublish;

use Illuminate\Database\Eloquent\Model;

class VesselFormSync extends Model
{
    protected $fillable = ['vessel_id', 'manual_form_id', 'file_hash'];
}
