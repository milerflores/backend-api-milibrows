<?php

namespace App\Modules\Personal;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\ConfiguracionEstudio;
use App\Models\Empleado;
use App\Models\JornadaEmpleado;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\ServicioCita;
use App\Models\User;
use App\Modules\Agenda\CitaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Throwable;

class PersonalController extends Controller
{
    public function listar()
    {
        $p = Empleado::with(['usuario.persona.cliente', 'usuario.roles', 'jornada', 'servicios'])
            ->orderBy('id')
            ->paginate(30, ['*'], 'pagina');

        return [
            'datos' => $p->map(fn ($e) => $this->presentar($e)),
            'meta' => ['pagina' => $p->currentPage(), 'total' => $p->total(), 'ultima_pagina' => $p->lastPage()],
        ];
    }

    private function presentar(Empleado $e): array
    {
        return [
            'id' => $e->id,
            'cargo' => $e->cargo,
            'activo' => $e->activo,
            'admite_reservas' => $e->admite_reservas,
            'imagen_url' => $e->imagen_url,
            'cuenta' => $e->usuario->perfil(),
            'jornada' => $e->jornada,
            'servicios' => $e->servicios,
        ];
    }

    public function detalle(int $id)
    {
        $e = Empleado::with(['usuario.persona.cliente', 'usuario.roles', 'jornada', 'servicios', 'asignaciones.insumo'])
            ->findOrFail($id);

        return ['datos' => $this->presentar($e) + ['asignaciones' => $e->asignaciones]];
    }

    public function crear(Request $r)
    {
        $d = $r->validate([
            'nombre' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'dni' => 'nullable|digits:8|unique:personas,dni',
            'telefono' => 'nullable|string|max:30',
            'correo' => 'required|email|max:255|unique:usuarios,correo',
            'rol' => 'required|in:administracion,recepcion,estilista',
            'cargo' => 'required|string|max:255',
            'admite_reservas' => 'sometimes|boolean',
            'contrasena' => ['required', Password::min(10)->mixedCase()->numbers()],
        ]);
        $e = DB::transaction(function () use ($d) {
            $p = Persona::create([
                'nombre' => $d['nombre'],
                'apellido_paterno' => $d['apellido_paterno'],
                'apellido_materno' => $d['apellido_materno'] ?? null,
                'dni' => $d['dni'] ?? null,
                'telefono' => $d['telefono'] ?? null,
                'correo_contacto' => mb_strtolower($d['correo']),
            ]);
            $u = User::create([
                'id_persona' => $p->id,
                'correo' => mb_strtolower($d['correo']),
                'contrasena' => $d['contrasena'],
            ]);
            $u->roles()->attach(Rol::where('codigo', $d['rol'])->value('id'));

            return Empleado::create([
                'id_usuario' => $u->id,
                'cargo' => $d['cargo'],
                'admite_reservas' => $d['rol'] === 'estilista' && ($d['admite_reservas'] ?? false),
            ]);
        });

        return response()->json(['datos' => $this->presentar($e->fresh(['usuario.persona.cliente', 'usuario.roles', 'jornada', 'servicios']))], 201);
    }

    public function actualizar(Request $r, int $id)
    {
        $e = Empleado::findOrFail($id);
        $d = $r->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'apellido_paterno' => 'sometimes|required|string|max:100',
            'apellido_materno' => 'sometimes|nullable|string|max:100',
            'telefono' => 'sometimes|nullable|string|max:30',
            'correo_contacto' => 'sometimes|nullable|email|max:255',
            'cargo' => 'sometimes|required|string|max:255',
            'activo' => 'sometimes|boolean',
            'admite_reservas' => 'sometimes|boolean',
            'imagen' => 'sometimes|nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'eliminar_imagen' => 'sometimes|boolean',
        ], [
            'imagen.image' => 'Seleccione una imagen JPG, PNG o WebP válida.',
            'imagen.mimes' => 'La imagen debe ser JPEG, PNG o WebP.',
            'imagen.max' => 'La imagen no debe superar los 2 MB.',
            'imagen.uploaded' => 'La imagen supera el límite de carga del servidor.',
        ]);
        if ($r->hasFile('imagen') && $r->boolean('eliminar_imagen')) {
            throw ValidationException::withMessages(['imagen' => ['Seleccione una imagen nueva o elimine la actual.']]);
        }
        $nuevaRuta = $r->hasFile('imagen') ? $r->file('imagen')->store('estilistas', 'public') : null;
        abort_if($nuevaRuta === false, 500, 'No se pudo almacenar la imagen.');
        try {
            DB::transaction(function () use ($e, $d, $r, $nuevaRuta) {
                $e = Empleado::lockForUpdate()->findOrFail($e->id);
                $anterior = $e->imagen_ruta;
                $datosPersona = collect($d)->only(['nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'correo_contacto'])->all();
                if ($datosPersona) {
                    $e->usuario->persona->update($datosPersona);
                }
                $datosEmpleado = collect($d)->only(['cargo', 'activo', 'admite_reservas'])->all();
                if ($nuevaRuta || $r->boolean('eliminar_imagen')) {
                    $datosEmpleado['imagen_ruta'] = $nuevaRuta;
                }
                if ($datosEmpleado) {
                    $e->update($datosEmpleado);
                }
                abort_if($e->admite_reservas && ! $e->usuario->es('estilista'), 422, 'Solo estilistas admiten reservas.');
                if ($anterior && $anterior !== $e->imagen_ruta) {
                    DB::afterCommit(fn () => $this->eliminarImagen($anterior));
                }
            }, 3);
        } catch (Throwable $error) {
            if ($nuevaRuta) {
                $this->eliminarImagen($nuevaRuta);
            }
            throw $error;
        }

        return ['datos' => $this->presentar($e->fresh(['usuario.persona.cliente', 'usuario.roles', 'jornada', 'servicios']))];
    }

    public function eliminar(Request $r, int $id)
    {
        DB::transaction(function () use ($r, $id) {
            $e = Empleado::with('usuario')->lockForUpdate()->findOrFail($id);
            if ($e->id_usuario === $r->user()->id) {
                abort(409, 'No puedes eliminar tu propia cuenta.');
            }
            $tieneHistorial = ServicioCita::where('id_empleado', $e->id)->exists()
                || DB::table('asignaciones_insumos')->where('id_empleado', $e->id)->exists()
                || Cita::where('id_usuario_creador', $e->id_usuario)->exists()
                || DB::table('cobros')->where('id_usuario_creador', $e->id_usuario)->exists()
                || DB::table('descuentos_manuales_cita')->where('id_usuario', $e->id_usuario)->exists()
                || DB::table('historial_cita')->where('id_usuario_responsable', $e->id_usuario)->exists()
                || DB::table('movimientos_inventario')->where('id_usuario_creador', $e->id_usuario)->exists()
                || DB::table('movimientos_puntos')->where('id_usuario_creador', $e->id_usuario)->exists();
            if ($tieneHistorial) {
                abort(409, 'No se puede eliminar este registro porque tiene citas, cobros o movimientos asociados. Puedes desactivar su perfil y acceso.');
            }
            $imagen = $e->imagen_ruta;
            $usuario = $e->usuario;
            $personaId = $usuario->id_persona;
            JornadaEmpleado::where('id_empleado', $e->id)->delete();
            DB::table('servicios_por_empleado')->where('id_empleado', $e->id)->delete();
            $e->delete();
            $usuario->tokens()->delete();
            $usuario->roles()->detach();
            $usuario->delete();
            if (! DB::table('clientes')->where('id_persona', $personaId)->exists()) {
                Persona::where('id', $personaId)->delete();
            }
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

    public function cuenta(Request $r, int $id)
    {
        $e = Empleado::findOrFail($id);
        $d = $r->validate([
            'correo' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('usuarios', 'correo')->ignore($e->id_usuario)],
            'rol' => 'sometimes|required|in:administracion,recepcion,estilista',
            'activo' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($e, $d) {
            $u = User::lockForUpdate()->findOrFail($e->id_usuario);
            if (isset($d['correo'])) {
                $u->correo = mb_strtolower($d['correo']);
                $u->fecha_verificacion_correo = null;
            }
            if (isset($d['activo'])) {
                $u->activo = $d['activo'];
            }
            if (isset($d['rol'])) {
                $u->roles()->sync([Rol::where('codigo', $d['rol'])->value('id')]);
                if ($d['rol'] !== 'estilista') {
                    $e->update(['admite_reservas' => false]);
                }
            }
            $this->revocar($u);
            $u->save();
        });

        return ['datos' => $e->usuario()->with(['persona.cliente', 'roles', 'empleado'])->first()->perfil()];
    }

    private function revocar(User $u): void
    {
        $u->version_sesion++;
        $u->tokens()->delete();
    }

    public function contrasena(Request $r, int $id)
    {
        $r->validate(['contrasena' => ['required', Password::min(10)->mixedCase()->numbers()]]);
        DB::transaction(function () use ($r, $id) {
            $e = Empleado::findOrFail($id);
            $u = User::lockForUpdate()->findOrFail($e->id_usuario);
            $u->contrasena = $r->contrasena;
            $this->revocar($u);
            $u->save();
        });

        return response()->noContent();
    }

    public function jornada(Request $r, int $id)
    {
        $d = $r->validate(Horarios::reglas('jornada'));
        Horarios::validar($d['jornada']);
        DB::transaction(function () use ($d, $id) {
            ConfiguracionEstudio::lockForUpdate()->findOrFail(1);
            $e = Empleado::lockForUpdate()->findOrFail($id);
            $e->jornada()->delete();
            foreach ($d['jornada'] as $j) {
                $e->jornada()->create($j);
            }
            Horarios::validarCitas($id);
        });

        return ['datos' => JornadaEmpleado::where('id_empleado', $id)->orderBy('dia_semana')->orderBy('hora_inicio')->get()];
    }

    public function servicios(Request $r, int $id)
    {
        $d = $r->validate(['ids_servicios' => 'present|array|max:100', 'ids_servicios.*' => 'required|integer|distinct|exists:servicios,id']);
        DB::transaction(function () use ($d, $id) {
            $e = Empleado::lockForUpdate()->findOrFail($id);
            $pendientes = ServicioCita::with('cita')
                ->where('id_empleado', $id)
                ->whereIn('estado_servicio', ['pendiente', 'en_proceso'])
                ->whereHas('cita', fn ($q) => $q->where('estado', 'confirmada')->where('fecha', '>=', today()))
                ->get()
                ->filter(fn ($sc) => $sc->cita->fecha->setTimeFromTimeString($sc->hora_inicio)->isFuture());
            $retirados = $pendientes->pluck('id_servicio')->unique()->diff($d['ids_servicios']);
            abort_if($retirados->isNotEmpty(), 409, 'Hay citas futuras para los servicios retirados.');
            $e->servicios()->sync(array_fill_keys($d['ids_servicios'], ['activo' => true]));
        });

        return ['datos' => Empleado::findOrFail($id)->servicios];
    }

    public function atenciones(Request $r, int $id)
    {
        Empleado::findOrFail($id);
        $d = $r->validate([
            'fecha_desde' => 'nullable|date_format:Y-m-d',
            'fecha_hasta' => 'nullable|date_format:Y-m-d'.($r->filled('fecha_desde') ? '|after_or_equal:fecha_desde' : ''),
        ]);
        $q = Cita::with(['servicios.empleado.usuario.persona', 'servicios.descuentoManual.motivo', 'cliente.persona', 'nota', 'historial', 'cobros.metodo'])
            ->where('estado', 'completada')
            ->whereHas('servicios', fn ($q) => $q->where('id_empleado', $id))
            ->orderByDesc('fecha')
            ->orderByDesc('id');
        if (isset($d['fecha_desde'])) {
            $q->whereDate('fecha', '>=', $d['fecha_desde']);
        }
        if (isset($d['fecha_hasta'])) {
            $q->whereDate('fecha', '<=', $d['fecha_hasta']);
        }
        $p = $q->paginate(30, ['*'], 'pagina');

        return [
            'datos' => CitaResource::collection($p->items())->resolve($r),
            'meta' => ['pagina' => $p->currentPage(), 'total' => $p->total(), 'ultima_pagina' => $p->lastPage()],
        ];
    }
}
