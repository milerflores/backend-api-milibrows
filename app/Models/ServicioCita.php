<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServicioCita extends Model
{
    protected $table = 'servicios_cita';

    protected $guarded = ['id'];

    protected $attributes = [
        'estado_servicio' => 'pendiente',
        'monto_descuento_automatico' => '0.00',
    ];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'precio_lista' => 'decimal:2',
            'monto_descuento_automatico' => 'decimal:2',
            'precio_acordado' => 'decimal:2',
            'duracion_minutos' => 'integer',
            'fecha_inicio_real' => 'immutable_datetime',
            'fecha_finalizacion' => 'immutable_datetime',
        ];
    }

    public function cita()
    {
        return $this->belongsTo(Cita::class, 'id_cita');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'id_servicio');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleado::class, 'id_empleado');
    }

    public function nivel()
    {
        return $this->belongsTo(NivelFidelizacion::class, 'id_nivel');
    }

    public function descuentoManual()
    {
        return $this->hasOne(DescuentoManualCita::class, 'id_servicio_cita');
    }
}
