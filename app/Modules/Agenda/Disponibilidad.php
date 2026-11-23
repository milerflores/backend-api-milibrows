<?php

namespace App\Modules\Agenda;

use App\Models\Empleado;
use App\Models\HorarioEstudio;
use App\Models\Servicio;
use App\Models\ServicioCita;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class Disponibilidad
{
    public function servicios(array $ids, bool $publicos = false): Collection
    {
        $unicos = array_values(array_unique($ids));
        $s = Servicio::whereIn('id', $unicos)
            ->where('activo', true)
            ->when($publicos, fn ($q) => $q->where('visible_al_publico', true))
            ->get();
        abort_unless($s->count() === count($unicos), 422, 'Servicios no disponibles.');

        return collect($ids)->map(fn ($id) => $s->firstWhere('id', $id));
    }

    public function habilitada(Empleado $e, array $ids): bool
    {
        if (! ($e->activo && $e->admite_reservas && $e->usuario->activo && $e->usuario->es('estilista'))) {
            return false;
        }
        $unicos = array_values(array_unique($ids));
        if ($e->relationLoaded('servicios')) {
            $habilitados = $e->servicios
                ->filter(fn ($s) => (bool) ($s->pivot->activo ?? false))
                ->pluck('id');

            return collect($unicos)->diff($habilitados)->isEmpty();
        }

        return $e->servicios()->wherePivot('activo', true)->whereIn('servicios.id', $unicos)->count() === count($unicos);
    }

    public function consultar(array $ids, CarbonImmutable $dia, int $duracion, ?int $empleado = null, ?int $excluirCita = null): array
    {
        $empleados = Empleado::with(['usuario.roles', 'usuario.persona', 'jornada', 'servicios'])
            ->when($empleado, fn ($q) => $q->where('id', $empleado))
            ->get();
        $estudio = HorarioEstudio::where('dia_semana', $dia->isoWeekday())->get();

        $datos = [];
        foreach ($empleados as $e) {
            if (! $this->habilitada($e, $ids)) {
                continue;
            }
            $libres = $this->intervalos($e, $dia, $duracion, null, $excluirCita, $estudio);
            $datos[] = [
                'id_empleado' => $e->id,
                'nombre_completo' => $e->usuario->persona->nombre_completo,
                'duracion_minutos' => $duracion,
                'intervalos' => array_map(fn ($i) => [
                    'hora_inicio' => $i[0]->format('H:i'),
                    'hora_fin' => $i[1]->format('H:i'),
                    'fecha_inicio' => $i[0]->toIso8601String(),
                    'fecha_fin' => $i[1]->toIso8601String(),
                ], $libres),
            ];
        }

        return $datos;
    }

    public function intervalos(
        Empleado $e,
        CarbonImmutable $dia,
        int $duracion,
        ?int $excluirServicioCita = null,
        ?int $excluirCita = null,
        ?Collection $estudio = null,
    ): array {
        $dia = $dia->startOfDay();
        $estudio ??= HorarioEstudio::where('dia_semana', $dia->isoWeekday())->get();
        $jornadas = $e->relationLoaded('jornada')
            ? $e->jornada->where('dia_semana', $dia->isoWeekday())
            : $e->jornada()->where('dia_semana', $dia->isoWeekday())->get();

        $ocupadas = ServicioCita::where('id_empleado', $e->id)
            ->whereIn('estado_servicio', ['pendiente', 'en_proceso', 'realizado'])
            ->when($excluirServicioCita, fn ($q) => $q->where('id', '!=', $excluirServicioCita))
            ->when($excluirCita, fn ($q) => $q->where('id_cita', '!=', $excluirCita))
            ->whereHas('cita', fn ($q) => $q->whereDate('fecha', $dia->format('Y-m-d'))->where('estado', 'confirmada'))
            ->orderBy('hora_inicio')
            ->get()
            ->map(function ($sc) use ($dia) {
                $ini = $dia->setTimeFromTimeString($sc->hora_inicio);

                return [$ini, $ini->addMinutes($sc->duracion_minutos)];
            });

        $intersecciones = [];
        foreach ($estudio as $h) {
            foreach ($jornadas as $j) {
                $inicio = $dia->setTimeFromTimeString(max($h->hora_inicio, $j->hora_inicio));
                $fin = $dia->setTimeFromTimeString(min($h->hora_fin, $j->hora_fin));
                if ($inicio < $fin) {
                    $intersecciones[] = [$inicio, $fin];
                }
            }
        }
        usort($intersecciones, fn ($a, $b) => $a[0] <=> $b[0]);
        $tramos = [];
        foreach ($intersecciones as [$inicio, $fin]) {
            $ultimo = count($tramos) - 1;
            if ($ultimo >= 0 && $inicio <= $tramos[$ultimo][1]) {
                $tramos[$ultimo][1] = $tramos[$ultimo][1]->max($fin);
            } else {
                $tramos[] = [$inicio, $fin];
            }
        }

        $libres = [];
        $ahora = CarbonImmutable::now();
        foreach ($tramos as [$inicio, $fin]) {
            $inicio = $inicio->max($ahora);
            foreach ($ocupadas as [$ocuIni, $ocuFin]) {
                if ($ocuFin <= $inicio || $ocuIni >= $fin) {
                    continue;
                }
                $hasta = $ocuIni->min($fin);
                if ($inicio->addMinutes($duracion) <= $hasta) {
                    $libres[] = [$inicio, $hasta];
                }
                $inicio = $inicio->max($ocuFin);
            }
            if ($inicio->addMinutes($duracion) <= $fin) {
                $libres[] = [$inicio, $fin];
            }
        }

        return $libres;
    }

    public function validarBloque(
        Empleado $e,
        int $idServicio,
        CarbonImmutable $dia,
        string $horaInicio,
        int $duracion,
        ?int $excluirServicioCita = null,
        ?int $excluirCita = null,
    ): array {
        $inicio = $dia->startOfDay()->setTimeFromTimeString($horaInicio);
        $fin = $inicio->addMinutes($duracion);
        abort_unless($inicio->isFuture() && $this->habilitada($e, [$idServicio]), 422, 'Estilista o fecha no habilitada.');
        abort_unless($fin->isSameDay($inicio) || $fin->format('H:i:s') === '00:00:00', 422, 'El servicio excede el horario del día.');
        $cabe = collect($this->intervalos($e, $dia, $duracion, $excluirServicioCita, $excluirCita))
            ->contains(fn ($r) => $inicio >= $r[0] && $fin <= $r[1]);
        abort_unless($cabe, 409, 'El intervalo ya no está disponible.');

        return [$inicio, $fin];
    }
}
