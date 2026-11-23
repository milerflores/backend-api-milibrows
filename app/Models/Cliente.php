<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $guarded = ['id'];

    protected $attributes = ['activo' => true];

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    public $timestamps = true;

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    public function citas()
    {
        return $this->hasMany(Cita::class, 'id_cliente');
    }

    public function movimientosPuntos()
    {
        return $this->hasMany(MovimientoPuntos::class, 'id_cliente');
    }
}
