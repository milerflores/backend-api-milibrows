<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DescuentoManualCita extends Model
{
    protected $table = 'descuentos_manuales_cita';

    protected $primaryKey = 'id_servicio_cita';

    public $incrementing = false;

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
            'fecha_creacion' => 'datetime',
        ];
    }

    public function servicioCita()
    {
        return $this->belongsTo(ServicioCita::class, 'id_servicio_cita');
    }

    public function motivo()
    {
        return $this->belongsTo(MotivoDescuento::class, 'id_motivo_descuento');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }
}
