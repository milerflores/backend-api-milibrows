<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class SoloSesionWeb
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->user()?->currentAccessToken();
        abort_if($token && ! $token instanceof TransientToken, 403, 'Esta operación requiere sesión web.');

        return $next($request);
    }
}
