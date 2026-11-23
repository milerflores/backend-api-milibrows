<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'empleados';

    protected $guarded = ['id'];

    protected $attributes = ['activo' => true, 'admite_reservas' => false];

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    public $timestamps = true;

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'admite_reservas' => 'boolean'];
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'id_usuario');
    }

    public function jornada()
    {
        return $this->hasMany(JornadaEmpleado::class, 'id_empleado');
    }

    public function servicios()
    {
        return $this->belongsToMany(Servicio::class, 'servicios_por_empleado', 'id_empleado', 'id_servicio')->withPivot('activo');
    }

    public function serviciosCita()
    {
        return $this->hasMany(ServicioCita::class, 'id_empleado');
    }

    public function asignaciones()
    {
        return $this->hasMany(AsignacionInsumo::class, 'id_empleado');
    }
}
