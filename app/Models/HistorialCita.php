<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HistorialCita extends Model
{
    protected $table = 'historial_cita';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'numero_revision' => 'integer',
            'detalle_cambios' => 'array',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'id_cita');
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'id_usuario_responsable');
    }
}
