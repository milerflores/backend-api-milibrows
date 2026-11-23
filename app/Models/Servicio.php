<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Servicio extends Model
{
    protected $table = 'servicios';

    protected $guarded = ['id'];

    protected $attributes = ['activo' => true, 'visible_al_publico' => true];

    protected $hidden = ['imagen_ruta'];

    protected $appends = ['imagen_url'];

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    public $timestamps = true;

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen_ruta
            ? '/api/v1/servicios/'.$this->id.'/imagen?v='.sha1($this->imagen_ruta)
            : null;
    }

    protected function casts(): array
    {
        return [
            'precio' => 'decimal:2',
            'duracion_minutos' => 'integer',
            'activo' => 'boolean',
            'visible_al_publico' => 'boolean',
        ];
    }
}
