<?php

namespace App\Modules\Administracion;

use App\Http\Controllers\Controller;
use App\Models\Aviso;
use App\Models\Cita;
use App\Models\Cobro;
use App\Models\ConfiguracionEstudio;
use App\Models\HorarioEstudio;
use App\Models\Insumo;
use App\Models\Servicio;
use App\Models\ServicioCita;
use App\Modules\Personal\Horarios;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class AdministracionController extends Controller
{
    public function servicios()
    {
        return Api::pagina(Servicio::orderBy('nombre'));
    }

    public function guardarServicio(Request $r, ?int $id = null)
    {
        if ($id) {
            Servicio::findOrFail($id);
        }
        $p = $id ? 'sometimes' : 'required';
        $d = $r->validate([
            'nombre' => "$p|required|string|max:255",
            'precio' => "$p|numeric|decimal:0,2|min:0|max:9999999999.99",
            'duracion_minutos' => "$p|integer|between:1,720",
            'activo' => 'sometimes|boolean',
            'visible_al_publico' => 'sometimes|boolean',
            'descripcion' => 'sometimes|nullable|string|max:5000',
            'imagen' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'eliminar_imagen' => 'sometimes|boolean',
        ], [
            'descripcion.max' => 'La descripción admite hasta 5000 caracteres.',
            'imagen.image' => 'Seleccione una imagen JPG, PNG o WebP válida.',
            'imagen.mimes' => 'La imagen debe ser JPEG, PNG o WebP.',
            'imagen.max' => 'La imagen no debe superar los 2 MB.',
            'imagen.uploaded' => 'La imagen supera el límite de carga del servidor.',
        ]);
        if ($r->hasFile('imagen') && $r->boolean('eliminar_imagen')) {
            throw ValidationException::withMessages(['imagen' => ['Seleccione una imagen nueva o elimine la actual.']]);
        }
        $nuevaRuta = $r->hasFile('imagen') ? $r->file('imagen')->store('servicios', 'public') : null;
        abort_if($nuevaRuta === false, 500, 'No se pudo almacenar la imagen.');
        try {
            $s = DB::transaction(function () use ($id, $d, $r, $nuevaRuta) {
                $s = $id ? Servicio::lockForUpdate()->findOrFail($id) : new Servicio;
                $anterior = $s->imagen_ruta;
                $s->fill(collect($d)->except(['imagen', 'eliminar_imagen'])->all());
                if ($nuevaRuta || $r->boolean('eliminar_imagen')) {
                    $s->imagen_ruta = $nuevaRuta;
                }
                $s->save();
                if ($anterior && $anterior !== $s->imagen_ruta) {
                    DB::afterCommit(fn () => $this->eliminarImagen($anterior));
                }

                return $s;
            }, 3);
        } catch (Throwable $error) {
            if ($nuevaRuta) {
                $this->eliminarImagen($nuevaRuta);
            }
            throw $error;
        }

        return response()->json(['datos' => $s->fresh()], $id ? 200 : 201);
    }

    public function eliminarServicio(int $id)
    {
        DB::transaction(function () use ($id) {
            $s = Servicio::lockForUpdate()->findOrFail($id);
            if (ServicioCita::where('id_servicio', $s->id)->exists()) {
                abort(409, 'No se puede eliminar este servicio porque tiene citas registradas. Puedes desactivarlo para ocultarlo.');
            }
            $imagen = $s->imagen_ruta;
            DB::table('servicios_por_empleado')->where('id_servicio', $s->id)->delete();
            $s->delete();
            if ($imagen) {
                DB::afterCommit(fn () => $this->eliminarImagen($imagen));
            }
        }, 3);

        return response()->noContent();
    }

    private function eliminarImagen(string $ruta): void
    {
        try {
            Storage::disk('public')->delete($ruta);
        } catch (Throwable $error) {
            report($error);
        }
    }

    public function horario()
    {
        return ['datos' => HorarioEstudio::orderBy('dia_semana')->orderBy('hora_inicio')->get()];
    }

    public function guardarHorario(Request $r)
    {
        $d = $r->validate(Horarios::reglas('horario'));
        Horarios::validar($d['horario']);
        DB::transaction(function () use ($d) {
            ConfiguracionEstudio::lockForUpdate()->findOrFail(1);
            HorarioEstudio::query()->delete();
            foreach ($d['horario'] as $h) {
                HorarioEstudio::create($h + ['id_estudio' => 1]);
            }
            Horarios::validarCitas();
        });

        return $this->horario();
    }

    public function configuracion()
    {
        return ['datos' => ConfiguracionEstudio::findOrFail(1)];
    }

    public function guardarConfiguracion(Request $r)
    {
        $d = $r->validate([
            'nombre_estudio' => 'sometimes|required|string|max:255',
            'monto_por_punto' => 'sometimes|required|numeric|decimal:0,2|gt:0|max:9999999999.99',
            'canal_notificacion' => 'sometimes|nullable|in:correo',
            'anticipacion_recordatorio_min' => 'sometimes|required|integer|between:1,10080',
        ]);
        DB::transaction(function () use ($d) {
            $cfg = ConfiguracionEstudio::lockForUpdate()->findOrFail(1);
            $cfg->update($d);
            if (array_key_exists('canal_notificacion', $d) || array_key_exists('anticipacion_recordatorio_min', $d)) {
                $this->recalcularRecordatorios($cfg);
            }
        });

        return $this->configuracion();
    }

    public function notificaciones()
    {
        return ['datos' => ConfiguracionEstudio::findOrFail(1)->only(['canal_notificacion', 'anticipacion_recordatorio_min'])];
    }

    public function guardarNotificaciones(Request $r)
    {
        $d = $r->validate([
            'canal_notificacion' => 'present|nullable|in:correo',
            'anticipacion_recordatorio_min' => 'required|integer|between:1,10080',
        ]);
        DB::transaction(function () use ($d) {
            $cfg = ConfiguracionEstudio::lockForUpdate()->findOrFail(1);
            $cfg->update($d);
            $this->recalcularRecordatorios($cfg);
        });

        return $this->notificaciones();
    }

    private function recalcularRecordatorios(ConfiguracionEstudio $cfg): void
    {
        $avisos = Aviso::with('cita.servicios')
            ->whereIn('estado', ['pendiente', 'sin_configurar'])
            ->where('tipo', 'recordatorio')
            ->get();
        foreach ($avisos as $a) {
            $inicio = $a->cita?->fechaHoraInicio();
            if ($inicio) {
                $a->update([
                    'fecha_programada' => $inicio->subMinutes($cfg->anticipacion_recordatorio_min),
                    'canal' => $cfg->canal_notificacion,
                ]);
            }
        }
    }

    public function resumen(Request $r)
    {
        $d = $r->validate([
            'fecha_desde' => 'nullable|date_format:Y-m-d',
            'fecha_hasta' => 'nullable|date_format:Y-m-d'.($r->filled('fecha_desde') ? '|after_or_equal:fecha_desde' : ''),
        ]);
        $citas = Cita::query();
        $cobros = Cobro::where('estado', 'registrado');
        foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $f => $op) {
            if (isset($d[$f])) {
                $citas->whereDate('fecha', $op, $d[$f]);
                $cobros->whereDate('fecha_cobro', $op, $d[$f]);
            }
        }

        return ['datos' => [
            'citas' => (clone $citas)->count(),
            'estados' => (clone $citas)->selectRaw('estado, COUNT(*) as cantidad')->groupBy('estado')->get(),
            'servicios_realizados' => ServicioCita::whereIn('id_cita', (clone $citas)->select('id'))->where('estado_servicio', 'realizado')->count(),
            'visitas' => ServicioCita::whereIn('id_cita', (clone $citas)->select('id'))->where('estado_servicio', 'realizado')->distinct()->count('id_cita'),
            'ingresos' => number_format((float) $cobros->sum('importe'), 2, '.', ''),
            'alertas_reposicion' => Insumo::with('categoria')->where('activo', true)->whereColumn('existencias_actuales', '<=', 'existencias_minimas')->get(),
        ]];
    }
}
