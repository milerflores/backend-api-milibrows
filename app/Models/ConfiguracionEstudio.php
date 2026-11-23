<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ConfiguracionEstudio extends Model
{
    protected $table = 'configuracion_estudio';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'anticipacion_recordatorio_min' => 'integer',
            'monto_por_punto' => 'decimal:2',
        ];
    }
}
