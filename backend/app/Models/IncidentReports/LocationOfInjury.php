<?php

namespace App\Models\IncidentReports;

use Illuminate\Database\Eloquent\Model;

class LocationOfInjury extends Model
{
    protected $table = 'locations_of_injury';

    protected $fillable = ['body_part'];
}
