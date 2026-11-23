<?php

use App\Http\Middleware\AccesoHabilitado;
use App\Http\Middleware\RolRequerido;
use App\Http\Middleware\SoloSesionWeb;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();
        $middleware->alias([
            'habilitado' => AccesoHabilitado::class,
            'sesion.web' => SoloSesionWeb::class,
            'rol' => RolRequerido::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (QueryException $e, Request $r) {
            if (! $r->is('api/*')) {
                return null;
            }
            $codigo = $e->errorInfo[1] ?? null;
            if ($codigo === 1062) {
                return response()->json(['mensaje' => 'El registro ya existe o la operación entra en conflicto.'], 409);
            }
            if ($codigo === 1644) {
                return response()->json(['mensaje' => $e->errorInfo[2] ?? 'La operación incumple una regla de negocio.'], 409);
            }
            if ($codigo === 3819) {
                return response()->json(['mensaje' => 'Los datos no cumplen las restricciones del sistema.'], 422);
            }
        });
        $exceptions->respond(function (Response $response) {
            if (request()->is('api/*') && $response->getStatusCode() >= 400) {
                $status = $response->getStatusCode();
                $original = json_decode($response->getContent(), true) ?? [];
                $mensaje = match ($status) {
                    401 => 'Debe iniciar sesión.', 403 => $original['message'] ?? 'Acceso no autorizado.',
                    404 => 'Registro o ruta no encontrado.', 419 => 'El token CSRF es inválido o expiró.',
                    422 => 'Los datos no son válidos.', 429 => 'Demasiados intentos. Intente más tarde.',
                    default => $status >= 500 ? 'Error interno del servidor.' : ($original['mensaje'] ?? $original['message'] ?? 'No se pudo completar la operación.'),
                };
                $datos = ['mensaje' => $mensaje];
                if (isset($original['errors'])) {
                    $datos['errores'] = $original['errors'];
                }

                return response()->json($datos, $status)->withHeaders(collect($response->headers->all())->except(['content-type', 'content-length'])->all());
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
