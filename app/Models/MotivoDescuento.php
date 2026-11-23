<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MotivoDescuento extends Model
{
    protected $table = 'motivos_descuento';

    protected $guarded = ['id'];

    protected $attributes = ['activo' => true];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }
}
