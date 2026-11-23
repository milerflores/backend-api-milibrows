<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotaCita extends Model
{
    protected $table = 'notas_cita';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $primaryKey = 'id_cita';

    public $incrementing = false;
}
