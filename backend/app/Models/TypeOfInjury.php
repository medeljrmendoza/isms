<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TypeOfInjury extends Model
{
    protected $table = 'types_of_injury';

    protected $fillable = ['name'];
}
