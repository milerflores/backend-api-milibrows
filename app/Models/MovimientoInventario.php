<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'variacion_cantidad' => 'integer',
            'existencias_antes' => 'integer',
            'existencias_despues' => 'integer',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'id_insumo');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'id_usuario_creador');
    }

    public function asignacion()
    {
        return $this->belongsTo(AsignacionInsumo::class, 'id_asignacion');
    }
}
