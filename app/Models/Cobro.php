<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cobro extends Model
{
    protected $table = 'cobros';

    protected $guarded = ['id'];

    protected $attributes = ['estado' => 'registrado'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'importe' => 'decimal:2',
            'fecha_cobro' => 'datetime',
        ];
    }

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'id_cita');
    }

    public function metodo()
    {
        return $this->belongsTo(MetodoPago::class, 'id_metodo_pago');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'id_usuario_creador');
    }

    public function movimientosPuntos()
    {
        return $this->hasMany(MovimientoPuntos::class, 'id_cobro');
    }
}
