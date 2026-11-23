<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NivelFidelizacion extends Model
{
    protected $table = 'niveles_fidelizacion';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'orden' => 'integer',
            'puntos_minimos' => 'integer',
            'porcentaje_descuento' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }
}
