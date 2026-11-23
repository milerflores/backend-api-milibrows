<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }
}
