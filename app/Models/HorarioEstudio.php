<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HorarioEstudio extends Model
{
    protected $table = 'horarios_estudio';

    protected $guarded = ['id'];

    public $timestamps = false;
}
