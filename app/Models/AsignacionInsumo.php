<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AsignacionInsumo extends Model
{
    protected $table = 'asignaciones_insumos';

    protected $guarded = ['id'];

    protected $attributes = ['estado' => 'en_uso'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cantidad_entregada' => 'integer',
            'fecha_entrega' => 'datetime',
            'fecha_agotamiento' => 'datetime',
        ];
    }

    public function insumo()
    {
        return $this->belongsTo(Insumo::class, 'id_insumo');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_empleado');
    }

    public function movimiento()
    {
        return $this->hasOne(MovimientoInventario::class, 'id_asignacion');
    }
}
