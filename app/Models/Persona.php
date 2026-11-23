<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Persona extends Model
{
    protected $table = 'personas';

    protected $guarded = ['id'];

    protected $appends = ['nombre_completo'];

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    public $timestamps = true;

    public function getNombreCompletoAttribute(): string
    {
        return trim(implode(' ', array_filter([
            $this->nombre,
            $this->apellido_paterno,
            $this->apellido_materno,
        ], fn ($v) => $v !== null && $v !== '')));
    }

    public function cliente()
    {
        return $this->hasOne(Cliente::class, 'id_persona');
    }

    public function usuario()
    {
        return $this->hasOne(User::class, 'id_persona');
    }
}
