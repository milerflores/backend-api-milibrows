<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MovimientoPuntos extends Model
{
    protected $table = 'movimientos_puntos';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'puntos' => 'integer',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function cobro()
    {
        return $this->belongsTo(Cobro::class, 'id_cobro');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'id_usuario_creador');
    }
}
