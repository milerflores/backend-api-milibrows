<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JornadaEmpleado extends Model
{
    protected $table = 'jornadas_empleados';

    protected $guarded = ['id'];

    public $timestamps = false;
}
