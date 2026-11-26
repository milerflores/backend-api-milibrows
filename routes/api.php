<?php

use App\Modules\Acceso\AccesoController as Acceso;
use App\Modules\Administracion\AdministracionController as Administracion;
use App\Modules\Agenda\AgendaController as Agenda;
use App\Modules\Caja\CajaController as Caja;
use App\Modules\Clientes\ClientesController as Clientes;
use App\Modules\Fidelizacion\FidelizacionController as Fidelizacion;
use App\Modules\Inventario\InventarioController as Inventario;
use App\Modules\Personal\PersonalController as Personal;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Controllers\CsrfCookieController;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::prefix('v1')->group(function () {
    Route::get('servicios', [Agenda::class, 'catalogo']);
    Route::get('servicios/{id}/imagen', [Agenda::class, 'imagen'])->whereNumber('id');
    Route::get('estilistas', [Agenda::class, 'estilistas']);
    Route::get('estilistas/{id}/imagen', [Agenda::class, 'imagenEstilista'])->whereNumber('id');
    Route::get('disponibilidad', [Agenda::class, 'disponibilidad'])->middleware('throttle:60,1');

    Route::middleware(['auth:sanctum', 'habilitado'])->group(function () {
        Route::get('mi-perfil', [Clientes::class, 'perfil']);
        Route::middleware('rol:cliente')->group(function () {
            Route::patch('mi-perfil', [Clientes::class, 'actualizarPerfil']);
            Route::get('mi-perfil/historial', [Clientes::class, 'historial']);
            Route::get('mi-perfil/pagos', [Clientes::class, 'pagos']);
            Route::get('mi-perfil/fidelizacion', [Clientes::class, 'fidelizacion']);
        });

        Route::middleware(['sesion.web', 'rol:administracion,recepcion'])->group(function () {
            Route::get('caja/metodos-pago', [Caja::class, 'metodosPago']);
            Route::get('caja/cobros', [Caja::class, 'cobros']);
            Route::post('caja/cobros', [Caja::class, 'cobrar']);
            Route::post('caja/cobros/{id}/anular', [Caja::class, 'anular'])->whereNumber('id');
            Route::get('fidelizacion/motivos-descuento', [Fidelizacion::class, 'motivosDescuento']);
            Route::put('citas/{id}/servicios/{idServicioCita}/descuento-manual', [Agenda::class, 'aplicarDescuentoManual'])
                ->whereNumber(['id', 'idServicioCita']);
            Route::delete('citas/{id}/servicios/{idServicioCita}/descuento-manual', [Agenda::class, 'eliminarDescuentoManual'])
                ->whereNumber(['id', 'idServicioCita']);
            Route::get('clientes', [Clientes::class, 'listar']);
            Route::post('clientes', [Clientes::class, 'crear']);
            Route::get('clientes/{id}', [Clientes::class, 'detalle'])->whereNumber('id');
            Route::patch('clientes/{id}', [Clientes::class, 'actualizar'])->whereNumber('id');
            Route::delete('clientes/{id}', [Clientes::class, 'eliminar'])->whereNumber('id');
            Route::get('clientes/{id}/historial', [Clientes::class, 'historial'])->whereNumber('id');
            Route::get('clientes/{id}/fidelizacion', [Clientes::class, 'fidelizacion'])->whereNumber('id');
        });

        Route::middleware(['sesion.web', 'rol:administracion'])->group(function () {
            Route::get('administracion/servicios', [Administracion::class, 'servicios']);
            Route::post('administracion/servicios', [Administracion::class, 'guardarServicio']);
            Route::patch('administracion/servicios/{id}', [Administracion::class, 'guardarServicio'])->whereNumber('id');
            Route::delete('administracion/servicios/{id}', [Administracion::class, 'eliminarServicio'])->whereNumber('id');
            Route::get('configuracion/estudio', [Administracion::class, 'configuracion']);
            Route::put('configuracion/estudio', [Administracion::class, 'guardarConfiguracion']);
            Route::get('configuracion/horario-estudio', [Administracion::class, 'horario']);
            Route::put('configuracion/horario-estudio', [Administracion::class, 'guardarHorario']);
            Route::get('configuracion/notificaciones', [Administracion::class, 'notificaciones']);
            Route::put('configuracion/notificaciones', [Administracion::class, 'guardarNotificaciones']);
            Route::get('administracion/resumen', [Administracion::class, 'resumen']);
            Route::get('caja/ingresos', [Caja::class, 'ingresos']);
            Route::get('inventario/insumos', [Inventario::class, 'insumos']);
            Route::post('inventario/insumos', [Inventario::class, 'guardarInsumo']);
            Route::get('inventario/insumos/{id}', [Inventario::class, 'detalle'])->whereNumber('id');
            Route::patch('inventario/insumos/{id}', [Inventario::class, 'guardarInsumo'])->whereNumber('id');
            Route::delete('inventario/insumos/{id}', [Inventario::class, 'eliminarInsumo'])->whereNumber('id');
            Route::get('inventario/movimientos', [Inventario::class, 'movimientos']);
            Route::post('inventario/movimientos', [Inventario::class, 'mover']);
            Route::post('inventario/asignaciones', [Inventario::class, 'asignar']);
            Route::get('fidelizacion/niveles', [Fidelizacion::class, 'niveles']);
            Route::put('fidelizacion/niveles', [Fidelizacion::class, 'guardarNiveles']);
            Route::post('fidelizacion/motivos-descuento', [Fidelizacion::class, 'guardarMotivoDescuento']);
            Route::patch('fidelizacion/motivos-descuento/{id}', [Fidelizacion::class, 'guardarMotivoDescuento'])->whereNumber('id');
            Route::get('fidelizacion/puntos', [Fidelizacion::class, 'movimientosPuntos']);
            Route::post('fidelizacion/puntos/ajustes', [Fidelizacion::class, 'ajustarPuntos']);
            Route::get('empleados', [Personal::class, 'listar']);
            Route::post('empleados', [Personal::class, 'crear']);
            Route::get('empleados/{id}', [Personal::class, 'detalle'])->whereNumber('id');
            Route::patch('empleados/{id}', [Personal::class, 'actualizar'])->whereNumber('id');
            Route::delete('empleados/{id}', [Personal::class, 'eliminar'])->whereNumber('id');
            Route::patch('empleados/{id}/cuenta', [Personal::class, 'cuenta'])->whereNumber('id');
            Route::put('empleados/{id}/cuenta/contrasena', [Personal::class, 'contrasena'])->whereNumber('id');
            foreach (['jornada', 'servicios'] as $accion) {
                Route::put('empleados/{id}/'.$accion, [Personal::class, $accion])->whereNumber('id');
            }
            Route::get('empleados/{id}/atenciones', [Personal::class, 'atenciones'])->whereNumber('id');
        });

        Route::middleware(['sesion.web', 'rol:administracion,estilista'])->group(function () {
            Route::get('inventario/asignaciones', [Inventario::class, 'asignaciones']);
            Route::post('inventario/asignaciones/{id}/agotar', [Inventario::class, 'agotar'])->whereNumber('id');
        });

        Route::get('citas', [Agenda::class, 'listar']);
        Route::post('citas', [Agenda::class, 'crear']);
        Route::get('citas/{id}', [Agenda::class, 'detalle'])->whereNumber('id');
        Route::get('citas/{id}/disponibilidad', [Agenda::class, 'disponibilidadReprogramacion'])
            ->whereNumber('id')->middleware('throttle:60,1');
        foreach (['reprogramar', 'cancelar'] as $accion) {
            Route::post('citas/{id}/'.$accion, [Agenda::class, 'cambiar'])->whereNumber('id');
        }
        foreach (['completar', 'marcar-inasistencia'] as $accion) {
            Route::post('citas/{id}/'.$accion, [Agenda::class, 'cambiar'])->whereNumber('id')->middleware('sesion.web');
        }
        Route::patch('citas/{id}/servicios/{idServicioCita}', [Agenda::class, 'estadoServicio'])
            ->whereNumber(['id', 'idServicioCita'])->middleware('sesion.web');
    });

    Route::middleware('web')->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->prefix('acceso')->group(function () {
        Route::get('cookie-csrf', [CsrfCookieController::class, 'show']);
        Route::post('iniciar-sesion', [Acceso::class, 'iniciar'])->middleware('throttle:acceso');
        Route::post('cerrar-sesion', [Acceso::class, 'cerrar'])->middleware(['auth:sanctum', 'sesion.web']);
        Route::middleware(['auth:sanctum', 'habilitado:mfa', 'sesion.web', 'rol:administracion,recepcion,estilista', 'throttle:acceso'])
            ->prefix('segundo-factor')->group(function () {
                Route::post('activar', [Acceso::class, 'activar']);
                Route::get('qr', [Acceso::class, 'qr']);
                Route::post('confirmar', [Acceso::class, 'confirmar']);
                Route::post('verificar', [Acceso::class, 'verificar']);
            });
    });

    Route::prefix('acceso')->middleware('throttle:acceso')->group(function () {
        Route::post('registro', [Acceso::class, 'registro']);
        Route::post('solicitar-restablecimiento', [Acceso::class, 'solicitar']);
        Route::post('restablecer-contrasena', [Acceso::class, 'restablecer']);
        Route::post('token-movil', [Acceso::class, 'token']);
    });
    Route::delete('acceso/token-movil', [Acceso::class, 'revocar'])->middleware(['auth:sanctum', 'habilitado']);
});
