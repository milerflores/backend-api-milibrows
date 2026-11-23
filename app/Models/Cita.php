<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    protected $attributes = ['estado' => 'confirmada', 'numero_revision' => 1];

    protected $table = 'citas';

    protected $guarded = ['id'];

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    public $timestamps = true;

    protected function casts(): array
    {
        return [
            'fecha' => 'immutable_date',
            'fecha_finalizacion' => 'immutable_datetime',
            'numero_revision' => 'integer',
        ];
    }

    public function fechaHoraInicio(): ?CarbonImmutable
    {
        $servicios = $this->relationLoaded('servicios') ? $this->servicios : $this->servicios()->get();
        $activos = $servicios->whereIn('estado_servicio', ['pendiente', 'en_proceso', 'realizado']);
        $base = $activos->isNotEmpty() ? $activos : $servicios;
        $hora = $base->min('hora_inicio');

        return $hora ? $this->fecha->setTimeFromTimeString($hora) : null;
    }

    public function fechaHoraFin(): ?CarbonImmutable
    {
        $servicios = $this->relationLoaded('servicios') ? $this->servicios : $this->servicios()->get();
        $activos = $servicios->whereIn('estado_servicio', ['pendiente', 'en_proceso', 'realizado']);
        $base = $activos->isNotEmpty() ? $activos : $servicios;
        $fin = null;
        foreach ($base as $s) {
            $termino = $this->fecha->setTimeFromTimeString($s->hora_inicio)->addMinutes($s->duracion_minutos);
            $fin = $fin ? $fin->max($termino) : $termino;
        }

        return $fin;
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'id_usuario_creador');
    }

    public function servicios()
    {
        return $this->hasMany(ServicioCita::class, 'id_cita')->orderBy('hora_inicio')->orderBy('id');
    }

    public function historial()
    {
        return $this->hasMany(HistorialCita::class, 'id_cita')->orderBy('numero_revision');
    }

    public function nota()
    {
        return $this->hasOne(NotaCita::class, 'id_cita');
    }

    public function cobros()
    {
        return $this->hasMany(Cobro::class, 'id_cita')->orderBy('id');
    }
}
