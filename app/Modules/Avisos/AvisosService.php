<?php

namespace App\Modules\Avisos;

use App\Models\Aviso;
use App\Models\Cita;
use App\Models\ConfiguracionEstudio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class AvisosService
{
    public function programar(Cita $c): void
    {
        $inicio = $c->fechaHoraInicio();
        if (! $inicio) {
            return;
        }
        $cfg = ConfiguracionEstudio::findOrFail(1);
        $idHistorial = $c->historial()->where('numero_revision', $c->numero_revision)->value('id');
        foreach (['confirmacion' => now(), 'recordatorio' => $inicio->subMinutes($cfg->anticipacion_recordatorio_min)] as $tipo => $fecha) {
            $a = Aviso::firstOrCreate(
                ['clave_deduplicacion' => "cita:{$c->id}:{$c->numero_revision}:{$tipo}"],
                [
                    'id_cita' => $c->id,
                    'id_historial_cita' => $idHistorial,
                    'revision_cita' => $c->numero_revision,
                    'tipo' => $tipo,
                    'canal' => $cfg->canal_notificacion,
                    'fecha_programada' => $fecha->max(now()),
                    'estado' => 'pendiente',
                ]
            );
            if ($tipo === 'confirmacion') {
                DB::afterCommit(fn () => EnviarAviso::dispatch($a->id));
            }
        }
    }

    public function invalidar(Cita $c): void
    {
        Aviso::where('id_cita', $c->id)
            ->whereIn('estado', ['pendiente', 'sin_configurar'])
            ->update(['estado' => 'invalidada']);
    }

    public function enviar(int $id): void
    {
        $referencia = Aviso::find($id);
        if (! $referencia) {
            return;
        }
        DB::transaction(function () use ($referencia) {
            $c = Cita::with(['servicios', 'cliente.persona.usuario'])->lockForUpdate()->findOrFail($referencia->id_cita);
            $a = Aviso::lockForUpdate()->findOrFail($referencia->id);
            if (! in_array($a->estado, ['pendiente', 'sin_configurar'], true) || $a->fecha_programada->isFuture()) {
                return;
            }
            $inicio = $c->fechaHoraInicio();
            if ($c->estado !== 'confirmada' || $c->numero_revision !== $a->revision_cita || ! $inicio || ! $inicio->isFuture()) {
                $a->update(['estado' => 'invalidada']);

                return;
            }
            $canal = ConfiguracionEstudio::findOrFail(1)->canal_notificacion;
            $correo = $c->cliente->persona->correo_contacto ?? $c->cliente->persona->usuario?->correo;
            if ($canal !== 'correo' || ! $correo) {
                $a->update(['estado' => 'sin_configurar']);

                return;
            }
            try {
                Mail::raw(
                    'Su cita en MiliBrows Studio está confirmada para '.$inicio->format('Y-m-d H:i').' ('.config('app.timezone').').',
                    fn ($m) => $m->to($correo)->subject($a->tipo === 'confirmacion' ? 'Confirmación de cita' : 'Recordatorio de cita')
                );
                $a->update(['estado' => 'enviada', 'canal' => $canal, 'fecha_envio' => now()]);
            } catch (\Throwable $error) {
                report($error);
                // Único intento de envío: conserva estado fallida sin reintentos automáticos.
                $a->update(['estado' => 'fallida']);
            }
        }, 3);
    }
}
