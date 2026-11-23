<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Insumo extends Model
{
    protected $table = 'insumos';

    protected $guarded = ['id'];

    protected $attributes = [
        'existencias_actuales' => 0,
        'existencias_minimas' => 0,
        'activo' => true,
    ];

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'existencias_actuales' => 'integer',
            'existencias_minimas' => 'integer',
            'activo' => 'boolean',
        ];
    }

    public function categoria()
    {
        return $this->belongsTo(CategoriaInventario::class, 'id_categoria');
    }
}
