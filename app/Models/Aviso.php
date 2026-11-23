<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Aviso extends Model
{
    protected $table = 'notificaciones';

    protected $guarded = ['id'];

    protected $attributes = ['estado' => 'pendiente'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'revision_cita' => 'integer',
            'fecha_programada' => 'immutable_datetime',
            'fecha_envio' => 'datetime',
        ];
    }

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'id_cita');
    }

    public function historial()
    {
        return $this->belongsTo(HistorialCita::class, 'id_historial_cita');
    }
}
