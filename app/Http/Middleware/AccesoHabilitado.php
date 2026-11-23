<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\TransientToken;

class AccesoHabilitado
{
    public function handle(Request $request, Closure $next, string $modo = 'panel')
    {
        $u = $request->user();
        $clienteActivo = ! $u->es('cliente') || ($u->persona?->cliente?->activo ?? false);
        abort_unless($u->activo && (! $u->empleado || $u->empleado->activo) && $clienteActivo, 403, 'Cuenta inactiva.');
        $token = $u->currentAccessToken();
        $movil = $token && ! $token instanceof TransientToken;
        abort_if($movil && (! $u->es('cliente') || $u->roles->count() !== 1 || $u->empleado || ! $token->can('cliente:movil')), 403);
        if (! $movil && $request->hasSession()) {
            abort_unless((int) $request->session()->get('version_sesion', $u->version_sesion) === (int) $u->version_sesion, 401, 'La sesión ha expirado.');
        }
        if ($modo !== 'mfa' && ($u->es('administracion') || $u->fecha_confirmacion_mfa)) {
            abort_unless($request->hasSession(), 403, 'Se requiere una sesión con segundo factor.');
            abort_unless(! $movil && $u->fecha_confirmacion_mfa && (int) $request->session()->get('mfa_usuario') === $u->id, 403, 'Debe configurar y verificar el segundo factor.');
        }

        return $next($request);
    }
}
