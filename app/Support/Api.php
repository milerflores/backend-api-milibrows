<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

class Api
{
    public static function pagina(Builder $q): array
    {
        request()->validate(['pagina' => 'sometimes|integer|min:1']);
        $p = $q->paginate(30, ['*'], 'pagina');

        return ['datos' => $p->items(), 'meta' => ['pagina' => $p->currentPage(), 'total' => $p->total(), 'ultima_pagina' => $p->lastPage()]];
    }

    public static function unidades(string|int|float $valor, int $decimales = 2): int
    {
        return (int) round((float) $valor * (10 ** $decimales));
    }
}
