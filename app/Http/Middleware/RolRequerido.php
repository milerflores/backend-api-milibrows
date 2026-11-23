<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RolRequerido
{
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        abort_unless($request->user()->es(...$roles), 403, 'No tiene permiso para esta operación.');

        return $next($request);
    }
}
