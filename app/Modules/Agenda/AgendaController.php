<?php

namespace App\Modules\Agenda;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Servicio;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class AgendaController extends Controller
{
    public function catalogo()
    {
        return ['datos' => Servicio::where('activo', true)->where('visible_al_publico', true)->orderBy('nombre')->get()];
    }

    public function imagen(Request $r, int $id)
    {
        $s = Servicio::findOrFail($id);
        $esAdmin = $r->user('sanctum')?->es('administracion') ?? false;
        abort_unless($esAdmin || ($s->activo && $s->visible_al_publico), 404);
        abort_unless($s->imagen_ruta && Storage::disk('public')->exists($s->imagen_ruta), 404);

        return Storage::disk('public')->response($s->imagen_ruta, null, [
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function disponibilidad(Request $r, Disponibilidad $disponibilidad)
    {
        $d = $r->validate([
            'fecha' => 'required|date_format:Y-m-d|after_or_equal:today',
            'ids_servicios' => 'required|array|min:1|max:20',
            'ids_servicios.*' => 'required|integer|distinct|exists:servicios,id',
            'id_empleado' => 'nullable|integer|exists:empleados,id',
        ]);
        $duracion = $disponibilidad->servicios($d['ids_servicios'], true)->sum('duracion_minutos');

        return ['datos' => $disponibilidad->consultar($d['ids_servicios'], CarbonImmutable::parse($d['fecha']), $duracion, $d['id_empleado'] ?? null)];
    }

    public function disponibilidadReprogramacion(Request $r, int $id, Disponibilidad $disponibilidad)
    {
        $c = Cita::with('servicios')->findOrFail($id);
        Gate::authorize('reprogramar', $c);
        abort_unless($c->estado === 'confirmada', 409, 'La cita ya tiene un estado final.');
        $inicio = $c->fechaHoraInicio();
        abort_if($r->user()->es('cliente') && (! $inicio || ! $inicio->isFuture()), 409, 'Solo puede modificar citas futuras.');
        $d = $r->validate([
            'fecha' => 'required|date_format:Y-m-d|after_or_equal:today',
            'id_empleado' => 'nullable|integer|exists:empleados,id',
            'id_servicio_cita' => 'nullable|integer',
        ]);
        $servicios = $d['id_servicio_cita'] ?? null
            ? $c->servicios->where('id', $d['id_servicio_cita'])
            : $c->servicios->whereIn('estado_servicio', ['pendiente', 'en_proceso']);
        abort_if($servicios->isEmpty(), 404, 'No hay servicios disponibles para reprogramar.');

        return ['datos' => $disponibilidad->consultar(
            $servicios->pluck('id_servicio')->all(),
            CarbonImmutable::parse($d['fecha']),
            $servicios->sum('duracion_minutos'),
            $d['id_empleado'] ?? null,
            $c->id
        )];
    }

    public function crear(Request $r, AgendaService $agenda)
    {
        abort_unless($r->user()->es('administracion', 'recepcion', 'cliente'), 403);
        $d = $r->validate([
            'fecha' => 'required|date_format:Y-m-d|after_or_equal:today',
            'id_cliente' => 'sometimes|integer|exists:clientes,id',
            'servicios' => 'required|array|min:1|max:20',
            'servicios.*.id_servicio' => 'required|integer|exists:servicios,id',
            'servicios.*.id_empleado' => 'required|integer|exists:empleados,id',
            'servicios.*.hora_inicio' => 'required|date_format:H:i',
        ]);
        if ($r->user()->es('cliente')) {
            abort_if($r->has('id_cliente'), 403, 'La identidad se obtiene de la cuenta.');
            $d['id_cliente'] = $r->user()->idCliente();
        }
        abort_unless($d['id_cliente'] ?? null, 422, 'Indique id_cliente.');

        return response()->json(['datos' => (new CitaResource($agenda->reservar($r->user(), $d)))->resolve($r)], 201);
    }

    public function listar(Request $r)
    {
        $d = $r->validate([
            'fecha_desde' => 'nullable|date_format:Y-m-d',
            'fecha_hasta' => 'nullable|date_format:Y-m-d'.($r->filled('fecha_desde') ? '|after_or_equal:fecha_desde' : ''),
            'estado' => 'nullable|in:confirmada,completada,cancelada,no_asistio',
            'id_empleado' => 'nullable|integer',
            'id_cliente' => 'nullable|integer',
        ]);
        $u = $r->user();
        $q = Cita::with([
            'servicios.empleado.usuario.persona',
            'servicios.descuentoManual.motivo',
            'cliente.persona',
            'nota',
            'historial',
            'cobros.metodo',
        ]);
        if ($u->es('cliente')) {
            $q->where('id_cliente', $u->idCliente());
        } elseif ($u->es('estilista') && ! $u->es('administracion', 'recepcion')) {
            $idEmp = $u->empleado?->id ?? 0;
            $q->whereHas('servicios', fn ($q) => $q->where('id_empleado', $idEmp));
        } else {
            abort_unless($u->es('administracion', 'recepcion'), 403);
        }

        if (isset($d['estado'])) {
            $q->where('estado', $d['estado']);
        }
        if (isset($d['id_cliente'])) {
            $q->where('id_cliente', $d['id_cliente']);
        }
        if (isset($d['id_empleado'])) {
            $q->whereHas('servicios', fn ($q) => $q->where('id_empleado', $d['id_empleado']));
        }
        if (isset($d['fecha_desde'])) {
            $q->whereDate('fecha', '>=', $d['fecha_desde']);
        }
        if (isset($d['fecha_hasta'])) {
            $q->whereDate('fecha', '<=', $d['fecha_hasta']);
        }

        $p = $q->orderBy('fecha')->orderBy('id')->paginate(30, ['*'], 'pagina');

        return [
            'datos' => CitaResource::collection($p->items())->resolve($r),
            'meta' => ['pagina' => $p->currentPage(), 'total' => $p->total(), 'ultima_pagina' => $p->lastPage()],
        ];
    }

    public function detalle(Request $r, int $id)
    {
        $c = Cita::with([
            'servicios.empleado.usuario.persona',
            'servicios.descuentoManual.motivo',
            'cliente.persona',
            'nota',
            'historial',
            'cobros.metodo',
        ])->findOrFail($id);
        Gate::authorize('view', $c);

        return ['datos' => (new CitaResource($c))->resolve($r)];
    }

    public function cambiar(Request $r, int $id, AgendaService $agenda)
    {
        $c = Cita::with('servicios')->findOrFail($id);
        $accion = $r->segment(5);
        Gate::authorize(match ($accion) {
            'reprogramar' => 'reprogramar',
            'completar', 'marcar-inasistencia' => 'completar',
            default => 'update',
        }, $c);

        $d = match ($accion) {
            'reprogramar' => $r->validate([
                'fecha' => 'required|date_format:Y-m-d|after_or_equal:today',
                'servicios' => 'sometimes|array|min:1|max:20',
                'servicios.*.id_servicio_cita' => 'required|integer|distinct',
                'servicios.*.id_empleado' => 'sometimes|integer|exists:empleados,id',
                'servicios.*.hora_inicio' => 'sometimes|date_format:H:i',
                'motivo' => 'nullable|string|max:2000',
            ]),
            'cancelar' => $r->validate([
                'motivo' => ($r->user()->es('cliente') ? 'nullable' : 'required').'|string|max:2000',
            ]),
            'completar' => $r->validate([
                'servicios' => 'required|array|min:1|max:20',
                'servicios.*.id_servicio_cita' => 'required|integer|distinct',
                'servicios.*.estado_servicio' => 'required|in:realizado,cancelado,no_realizado',
                'servicios.*.motivo_estado_servicio' => 'nullable|string|max:2000',
                'nota' => 'sometimes|array:descripcion_tratamiento,observaciones',
                'nota.descripcion_tratamiento' => 'nullable|string|max:5000',
                'nota.observaciones' => 'nullable|string|max:5000',
            ]),
            default => [],
        };

        return ['datos' => (new CitaResource($agenda->cambiar($r->user(), $id, $accion, $d)))->resolve($r)];
    }

    public function estadoServicio(Request $r, int $id, int $idServicioCita, AgendaService $agenda)
    {
        $d = $r->validate([
            'estado_servicio' => 'required|in:en_proceso,realizado,cancelado,no_realizado',
            'motivo_estado_servicio' => 'nullable|string|max:2000',
        ]);

        return ['datos' => (new CitaResource($agenda->actualizarEstadoServicio($r->user(), $id, $idServicioCita, $d)))->resolve($r)];
    }

    public function aplicarDescuentoManual(Request $r, int $id, int $idServicioCita, AgendaService $agenda)
    {
        $d = $r->validate([
            'monto' => 'required|numeric|decimal:0,2|min:0|max:9999999999.99',
            'id_motivo_descuento' => 'nullable|integer|exists:motivos_descuento,id',
            'nota' => 'nullable|string|max:2000',
        ]);

        return ['datos' => (new CitaResource($agenda->aplicarDescuentoManual($r->user(), $id, $idServicioCita, $d)))->resolve($r)];
    }

    public function eliminarDescuentoManual(Request $r, int $id, int $idServicioCita, AgendaService $agenda)
    {
        return ['datos' => (new CitaResource($agenda->eliminarDescuentoManual($id, $idServicioCita)))->resolve($r)];
    }
}
