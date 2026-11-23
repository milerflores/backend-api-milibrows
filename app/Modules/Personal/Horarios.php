<?php

namespace App\Modules\Personal;

use App\Models\ServicioCita;
use App\Modules\Agenda\Disponibilidad;

class Horarios
{
    public static function reglas(string $campo): array
    {
        return [
            $campo => 'present|array|max:42',
            "$campo.*" => 'array:dia_semana,hora_inicio,hora_fin',
            "$campo.*.dia_semana" => 'required|integer|between:1,7',
            "$campo.*.hora_inicio" => 'required|date_format:H:i',
            "$campo.*.hora_fin" => 'required|date_format:H:i',
        ];
    }

    public static function validar(array $tramos): void
    {
        foreach (collect($tramos)->groupBy('dia_semana') as $dia) {
            $fin = '00:00';
            foreach ($dia->sortBy('hora_inicio') as $t) {
                abort_unless($t['hora_inicio'] < $t['hora_fin'] && $t['hora_inicio'] >= $fin, 422, 'Los tramos deben ser positivos y no superponerse.');
                $fin = $t['hora_fin'];
            }
        }
    }

    public static function validarCitas(?int $empleado = null): void
    {
        $disponibilidad = app(Disponibilidad::class);
        $servicios = ServicioCita::with(['cita', 'empleado.jornada'])
            ->whereIn('estado_servicio', ['pendiente', 'en_proceso'])
            ->when($empleado, fn ($q) => $q->where('id_empleado', $empleado))
            ->whereHas('cita', fn ($q) => $q->where('estado', 'confirmada')->where('fecha', '>=', today()))
            ->get();

        foreach ($servicios as $sc) {
            $inicio = $sc->cita->fecha->setTimeFromTimeString($sc->hora_inicio);
            if (! $inicio->isFuture()) {
                continue;
            }
            $fin = $inicio->addMinutes($sc->duracion_minutos);
            $libres = $disponibilidad->intervalos($sc->empleado, $sc->cita->fecha, $sc->duracion_minutos, $sc->id);
            abort_unless(
                collect($libres)->contains(fn ($r) => $inicio >= $r[0] && $fin <= $r[1]),
                409,
                'El cambio dejaría una cita confirmada fuera del horario.'
            );
        }
    }
}
