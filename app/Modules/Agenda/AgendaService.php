<?php

namespace App\Modules\Agenda;

use App\Models\Cita;
use App\Models\Cliente;
use App\Models\DescuentoManualCita;
use App\Models\Empleado;
use App\Models\HistorialCita;
use App\Models\MotivoDescuento;
use App\Models\NotaCita;
use App\Models\ServicioCita;
use App\Models\User;
use App\Modules\Avisos\AvisosService;
use App\Modules\Fidelizacion\FidelizacionService;
use App\Support\Api;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class AgendaService
{
    public function __construct(
        private Disponibilidad $disponibilidad,
        private FidelizacionService $fidelizacion,
    ) {}

    public function reservar(User $u, array $d): Cita
    {
        return DB::transaction(function () use ($u, $d) {
            $cliente = Cliente::lockForUpdate()->findOrFail($d['id_cliente']);
            abort_unless($cliente->activo, 409, 'El cliente está inactivo.');

            $fecha = CarbonImmutable::createFromFormat('Y-m-d', $d['fecha'])->startOfDay();
            $items = collect($d['servicios']);
            $empleadosIds = $items->pluck('id_empleado')->unique()->sort()->values()->all();
            $empleados = Empleado::with(['usuario.roles', 'jornada', 'servicios'])
                ->whereIn('id', $empleadosIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');
            abort_unless($empleados->count() === count($empleadosIds), 422, 'Uno o más empleados no existen.');

            $serviciosCatalogo = $this->disponibilidad
                ->servicios($items->pluck('id_servicio')->all(), $u->es('cliente'))
                ->keyBy('id');

            $bloquesPorEmpleado = [];
            $preparados = [];
            foreach ($items as $item) {
                $e = $empleados[$item['id_empleado']];
                $s = $serviciosCatalogo[$item['id_servicio']];
                [$inicio, $fin] = $this->disponibilidad->validarBloque(
                    $e,
                    $s->id,
                    $fecha,
                    $item['hora_inicio'],
                    $s->duracion_minutos
                );
                foreach ($bloquesPorEmpleado[$e->id] ?? [] as [$otroIni, $otroFin]) {
                    abort_if($inicio < $otroFin && $fin > $otroIni, 409, 'Los servicios seleccionados se superponen para la misma estilista.');
                }
                $bloquesPorEmpleado[$e->id][] = [$inicio, $fin];
                $cotizacion = $this->fidelizacion->cotizarServicio($cliente->id, $s->precio);
                $preparados[] = [
                    'id_servicio' => $s->id,
                    'id_empleado' => $e->id,
                    'id_nivel' => $cotizacion['nivel']->id,
                    'nombre_servicio_acordado' => $s->nombre,
                    'precio_lista' => $cotizacion['precio_lista'],
                    'monto_descuento_automatico' => $cotizacion['monto_descuento_automatico'],
                    'precio_acordado' => $cotizacion['precio_acordado'],
                    'duracion_minutos' => $s->duracion_minutos,
                    'hora_inicio' => $inicio->format('H:i:s'),
                    'estado_servicio' => 'pendiente',
                ];
            }

            $c = Cita::create([
                'id_cliente' => $cliente->id,
                'id_usuario_creador' => $u->id,
                'fecha' => $fecha->format('Y-m-d'),
                'origen' => $u->es('cliente') ? 'cliente' : 'panel',
                'estado' => 'confirmada',
                'numero_revision' => 1,
            ]);

            foreach ($preparados as $linea) {
                $c->servicios()->create($linea);
            }

            $this->registrarHistorial($c, $u, 'creada');
            app(AvisosService::class)->programar($c->fresh(['servicios', 'historial']));

            return $c->fresh(['servicios.empleado.usuario.persona', 'servicios.descuentoManual.motivo', 'cliente.persona', 'nota', 'historial', 'cobros.metodo']);
        }, 3);
    }

    public function cambiar(User $u, int $id, string $accion, array $d): Cita
    {
        return DB::transaction(function () use ($u, $id, $accion, $d) {
            $c = Cita::with(['servicios', 'cobros'])->lockForUpdate()->findOrFail($id);
            Gate::forUser($u)->authorize(match ($accion) {
                'reprogramar' => 'reprogramar',
                'completar', 'marcar-inasistencia' => 'completar',
                default => 'update',
            }, $c);

            abort_unless($c->estado === 'confirmada', 409, 'La cita ya tiene un estado final.');
            $inicioActual = $c->fechaHoraInicio();
            if ($u->es('cliente')) {
                abort_unless($inicioActual && $inicioActual->isFuture(), 409, 'Solo puede modificar citas futuras.');
            }

            if ($accion === 'reprogramar') {
                $this->aplicarReprogramacion($c, $d);
            } elseif ($accion === 'cancelar') {
                $c->servicios()->whereIn('estado_servicio', ['pendiente', 'en_proceso'])->update([
                    'estado_servicio' => 'cancelado',
                    'motivo_estado_servicio' => $d['motivo'] ?? null,
                ]);
                $c->estado = 'cancelada';
                $c->motivo_cancelacion = $d['motivo'] ?? null;
            } elseif ($accion === 'marcar-inasistencia') {
                abort_if($inicioActual && $inicioActual->isFuture(), 409, 'La cita aún no ha comenzado.');
                $c->servicios()->whereIn('estado_servicio', ['pendiente', 'en_proceso'])->update([
                    'estado_servicio' => 'no_realizado',
                    'motivo_estado_servicio' => 'No asistió',
                ]);
                $c->estado = 'no_asistio';
            } else {
                abort_if($inicioActual && $inicioActual->isFuture(), 409, 'La atención aún no ha comenzado.');
                $resultados = collect($d['servicios']);
                $ids = $c->servicios->pluck('id')->sort()->values()->all();
                abort_unless($resultados->pluck('id_servicio_cita')->sort()->values()->all() === $ids, 422, 'Debe indicar el resultado de todos los servicios de esta cita.');
                abort_unless($resultados->contains('estado_servicio', 'realizado'), 422, 'Para completar la cita debe realizarse al menos un servicio.');

                $ahora = now();
                foreach ($resultados as $s) {
                    abort_if($s['estado_servicio'] !== 'realizado' && empty(trim((string) ($s['motivo_estado_servicio'] ?? ''))), 422, 'Indique el motivo de cada servicio no realizado.');
                    $c->servicios()->where('id', $s['id_servicio_cita'])->update([
                        'estado_servicio' => $s['estado_servicio'],
                        'motivo_estado_servicio' => $s['motivo_estado_servicio'] ?? null,
                        'fecha_finalizacion' => $ahora,
                    ]);
                }
                $c->estado = 'completada';
                $c->fecha_finalizacion = $ahora;
                if (isset($d['nota'])) {
                    NotaCita::updateOrCreate(['id_cita' => $c->id], $d['nota']);
                }
            }

            $c->numero_revision++;
            $c->save();
            $this->registrarHistorial($c, $u, $accion, $d['motivo'] ?? null);
            app(AvisosService::class)->invalidar($c);
            if ($accion === 'reprogramar') {
                app(AvisosService::class)->programar($c->fresh(['servicios', 'historial']));
            }

            return $c->fresh(['servicios.empleado.usuario.persona', 'servicios.descuentoManual.motivo', 'cliente.persona', 'nota', 'historial', 'cobros.metodo']);
        }, 3);
    }

    public function actualizarEstadoServicio(User $u, int $idCita, int $idServicioCita, array $d): Cita
    {
        return DB::transaction(function () use ($u, $idCita, $idServicioCita, $d) {
            $c = Cita::with('servicios')->lockForUpdate()->findOrFail($idCita);
            Gate::forUser($u)->authorize('completar', $c);
            abort_unless($c->estado === 'confirmada', 409, 'La cita ya tiene un estado final.');
            $sc = $c->servicios->firstWhere('id', $idServicioCita);
            abort_unless($sc, 404, 'Servicio no encontrado en la cita.');
            if ($u->es('estilista') && ! $u->es('administracion', 'recepcion')) {
                abort_unless($u->empleado?->id === $sc->id_empleado, 403, 'Solo puede actualizar sus propios servicios.');
            }
            $estado = $d['estado_servicio'];
            if (in_array($estado, ['cancelado', 'no_realizado'], true)) {
                abort_if(empty(trim((string) ($d['motivo_estado_servicio'] ?? ''))), 422, 'Indique el motivo.');
            }
            $updates = [
                'estado_servicio' => $estado,
                'motivo_estado_servicio' => $d['motivo_estado_servicio'] ?? null,
            ];
            if ($estado === 'en_proceso' && ! $sc->fecha_inicio_real) {
                $updates['fecha_inicio_real'] = now();
            }
            if (in_array($estado, ['realizado', 'cancelado', 'no_realizado'], true)) {
                $updates['fecha_finalizacion'] = now();
            }
            $sc->update($updates);

            return $c->fresh(['servicios.empleado.usuario.persona', 'servicios.descuentoManual.motivo', 'cliente.persona', 'nota', 'historial', 'cobros.metodo']);
        }, 3);
    }

    public function aplicarDescuentoManual(User $u, int $idCita, int $idServicioCita, array $d): Cita
    {
        return DB::transaction(function () use ($u, $idCita, $idServicioCita, $d) {
            $c = Cita::with(['servicios', 'cobros'])->lockForUpdate()->findOrFail($idCita);
            abort_if($c->cobros->where('estado', 'registrado')->isNotEmpty(), 409, 'No se puede modificar descuentos de una cita con cobros registrados.');
            $sc = ServicioCita::lockForUpdate()->where('id_cita', $c->id)->findOrFail($idServicioCita);
            if (! empty($d['id_motivo_descuento'])) {
                abort_unless(MotivoDescuento::where('id', $d['id_motivo_descuento'])->where('activo', true)->exists(), 422, 'El motivo de descuento no está activo.');
            }
            $nota = isset($d['nota']) ? trim((string) $d['nota']) : null;
            abort_if(empty($d['id_motivo_descuento']) && ($nota === null || $nota === ''), 422, 'Indique un motivo o una observación para el descuento manual.');

            $base = Api::unidades($sc->precio_lista) - Api::unidades($sc->monto_descuento_automatico);
            $monto = Api::unidades($d['monto']);
            abort_if($monto < 0 || $monto > $base, 422, 'El descuento manual excede el precio disponible del servicio.');

            DescuentoManualCita::updateOrCreate(
                ['id_servicio_cita' => $sc->id],
                [
                    'id_motivo_descuento' => $d['id_motivo_descuento'] ?? null,
                    'id_usuario' => $u->id,
                    'monto' => number_format($monto / 100, 2, '.', ''),
                    'nota' => $nota !== '' ? $nota : null,
                    'fecha_creacion' => now(),
                ]
            );
            $sc->update(['precio_acordado' => number_format(($base - $monto) / 100, 2, '.', '')]);

            return $c->fresh(['servicios.empleado.usuario.persona', 'servicios.descuentoManual.motivo', 'cliente.persona', 'nota', 'historial', 'cobros.metodo']);
        }, 3);
    }

    public function eliminarDescuentoManual(int $idCita, int $idServicioCita): Cita
    {
        return DB::transaction(function () use ($idCita, $idServicioCita) {
            $c = Cita::with('cobros')->lockForUpdate()->findOrFail($idCita);
            abort_if($c->cobros->where('estado', 'registrado')->isNotEmpty(), 409, 'No se puede eliminar descuentos de una cita con cobros registrados.');
            $sc = ServicioCita::lockForUpdate()->where('id_cita', $c->id)->findOrFail($idServicioCita);
            DescuentoManualCita::where('id_servicio_cita', $sc->id)->delete();
            $base = Api::unidades($sc->precio_lista) - Api::unidades($sc->monto_descuento_automatico);
            $sc->update(['precio_acordado' => number_format($base / 100, 2, '.', '')]);

            return $c->fresh(['servicios.empleado.usuario.persona', 'servicios.descuentoManual.motivo', 'cliente.persona', 'nota', 'historial', 'cobros.metodo']);
        }, 3);
    }

    private function aplicarReprogramacion(Cita $c, array $d): void
    {
        $nuevaFecha = CarbonImmutable::createFromFormat('Y-m-d', $d['fecha'])->startOfDay();
        $cambiosPorId = collect($d['servicios'] ?? [])->keyBy('id_servicio_cita');
        $pendientes = $c->servicios->whereIn('estado_servicio', ['pendiente', 'en_proceso']);
        abort_if($pendientes->isEmpty(), 409, 'No hay servicios pendientes para reprogramar.');

        $empleadosIds = $pendientes->map(fn ($sc) => $cambiosPorId[$sc->id]['id_empleado'] ?? $sc->id_empleado)
            ->unique()->sort()->values()->all();
        $empleados = Empleado::with(['usuario.roles', 'jornada', 'servicios'])
            ->whereIn('id', $empleadosIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        $bloquesPorEmpleado = [];
        $actualizaciones = [];
        foreach ($pendientes as $sc) {
            $idEmp = $cambiosPorId[$sc->id]['id_empleado'] ?? $sc->id_empleado;
            $hora = $cambiosPorId[$sc->id]['hora_inicio'] ?? substr((string) $sc->hora_inicio, 0, 5);
            $e = $empleados[$idEmp] ?? null;
            abort_unless($e, 422, 'Estilista inválida.');
            [$inicio, $fin] = $this->disponibilidad->validarBloque(
                $e,
                $sc->id_servicio,
                $nuevaFecha,
                $hora,
                $sc->duracion_minutos,
                null,
                $c->id
            );
            foreach ($bloquesPorEmpleado[$e->id] ?? [] as [$otroIni, $otroFin]) {
                abort_if($inicio < $otroFin && $fin > $otroIni, 409, 'Los servicios reprogramados se superponen para la misma estilista.');
            }
            $bloquesPorEmpleado[$e->id][] = [$inicio, $fin];
            $actualizaciones[] = [$sc, $e->id, $inicio->format('H:i:s')];
        }

        // Actualizar primero la fecha de la cita y luego cada servicio para satisfacer los triggers.
        $c->fecha = $nuevaFecha->format('Y-m-d');
        $c->save();
        foreach ($actualizaciones as [$sc, $idEmp, $hora]) {
            $sc->update(['id_empleado' => $idEmp, 'hora_inicio' => $hora]);
        }
    }

    private function registrarHistorial(Cita $c, User $u, string $tipo, ?string $motivo = null): void
    {
        $c->load('servicios');
        HistorialCita::create([
            'id_cita' => $c->id,
            'id_usuario_responsable' => $u->id,
            'numero_revision' => $c->numero_revision,
            'tipo_cambio' => $tipo,
            'detalle_cambios' => [
                'estado' => $c->estado,
                'fecha' => $c->fecha->format('Y-m-d'),
                'servicios' => $c->servicios->map(fn ($s) => [
                    'id' => $s->id,
                    'id_servicio' => $s->id_servicio,
                    'id_empleado' => $s->id_empleado,
                    'hora_inicio' => substr((string) $s->hora_inicio, 0, 5),
                    'estado_servicio' => $s->estado_servicio,
                ])->values()->all(),
            ],
            'motivo' => $motivo,
            'fecha_creacion' => now(),
        ]);
    }
}
