<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VesselFormSync extends Model
{
    protected $fillable = ['vessel_id', 'manual_form_id', 'file_hash'];
}
