<?php

namespace App\Modules\Avisos;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class EnviarAviso implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $idAviso)
    {
        $this->afterCommit();
    }

    public function handle(AvisosService $avisos): void
    {
        $avisos->enviar($this->idAviso);
    }
}
