<?php

use App\Models\Aviso;
use App\Modules\Avisos\EnviarAviso;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('avisos:enviar', function () {
    Aviso::whereIn('estado', ['pendiente', 'sin_configurar'])
        ->where('fecha_programada', '<=', now())
        ->orderBy('id')
        ->chunkById(100, function ($avisos) {
            foreach ($avisos as $aviso) {
                EnviarAviso::dispatch($aviso->id);
            }
        });
})->purpose('Enviar avisos vigentes pendientes');
Schedule::command('avisos:enviar')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
