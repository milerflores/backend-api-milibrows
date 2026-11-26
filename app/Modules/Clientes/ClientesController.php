<?php

namespace App\Modules\Clientes;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Cobro;
use App\Models\Persona;
use App\Models\ServicioCita;
use App\Modules\Fidelizacion\FidelizacionService;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientesController extends Controller
{
    public function perfil(Request $r)
    {
        return ['datos' => $r->user()->load(['persona.cliente', 'roles', 'empleado'])->perfil()];
    }

    public function actualizarPerfil(Request $r)
    {
        $d = $r->validate([
            'nombre' => 'sometimes|required|string|max:100',
            'apellido_paterno' => 'sometimes|required|string|max:100',
            'apellido_materno' => 'sometimes|nullable|string|max:100',
            'telefono' => 'sometimes|nullable|string|max:30',
            'correo_contacto' => 'sometimes|nullable|email|max:255',
            'preferencias' => 'sometimes|nullable|string|max:2000',
        ]);
        $p = $r->user()->persona;
        DB::transaction(function () use ($p, $d) {
            $datosPersona = collect($d)->except('preferencias')->all();
            if ($datosPersona) {
                $p->update($datosPersona);
            }
            if (array_key_exists('preferencias', $d)) {
                $p->cliente->update(['preferencias' => $d['preferencias']]);
            }
        });

        return ['datos' => $r->user()->fresh(['persona.cliente', 'roles', 'empleado'])->perfil()];
    }

    public function listar(Request $r)
    {
        $r->validate([
            'buscar' => 'sometimes|string|max:100',
            'activo' => 'sometimes|boolean',
        ]);
        $q = Cliente::with('persona')
            ->when($r->has('activo'), fn ($q) => $q->where('activo', $r->boolean('activo')))
            ->when($r->filled('buscar'), function ($q) use ($r) {
                $termino = trim((string) $r->buscar);
                $q->whereHas('persona', fn ($q) => $q
                    ->where('dni', $termino)
                    ->orWhereRaw("CONCAT_WS(' ', nombre, apellido_paterno, apellido_materno) LIKE ?", ['%'.$termino.'%']));
            })
            ->orderBy('id');

        return Api::pagina($q);
    }

    public function crear(Request $r)
    {
        $d = $r->validate([
            'dni' => 'nullable|digits:8|unique:personas,dni',
            'nombre' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'telefono' => 'nullable|string|max:30',
            'correo_contacto' => 'nullable|email|max:255',
            'preferencias' => 'nullable|string|max:2000',
        ]);
        $c = DB::transaction(function () use ($d) {
            $p = Persona::create(collect($d)->except('preferencias')->all());

            return Cliente::create(['id_persona' => $p->id, 'preferencias' => $d['preferencias'] ?? null, 'activo' => true]);
        });

        return response()->json(['datos' => $c->load('persona')], 201);
    }

    public function detalle(int $id)
    {
        return ['datos' => Cliente::with('persona')->findOrFail($id)];
    }

    public function actualizar(Request $r, int $id)
    {
        $c = Cliente::findOrFail($id);
        $d = $r->validate([
            'dni' => ['sometimes', 'nullable', 'digits:8', Rule::unique('personas', 'dni')->ignore($c->id_persona)],
            'nombre' => 'sometimes|required|string|max:100',
            'apellido_paterno' => 'sometimes|required|string|max:100',
            'apellido_materno' => 'sometimes|nullable|string|max:100',
            'telefono' => 'sometimes|nullable|string|max:30',
            'correo_contacto' => 'sometimes|nullable|email|max:255',
            'preferencias' => 'sometimes|nullable|string|max:2000',
            'activo' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($c, $d) {
            $datosPersona = collect($d)->except(['preferencias', 'activo'])->all();
            if ($datosPersona) {
                $c->persona->update($datosPersona);
            }
            $datosCliente = collect($d)->only(['preferencias', 'activo'])->all();
            if ($datosCliente) {
                $c->update($datosCliente);
            }
        });

        return ['datos' => $c->fresh('persona')];
    }

    public function eliminar(int $id)
    {
        DB::transaction(function () use ($id) {
            $c = Cliente::lockForUpdate()->findOrFail($id);
            $tieneHistorial = DB::table('citas')->where('id_cliente', $c->id)->exists()
                || DB::table('movimientos_puntos')->where('id_cliente', $c->id)->exists();
            if ($tieneHistorial) {
                abort(409, 'No se puede eliminar esta cliente porque tiene citas o movimientos de puntos registrados.');
            }
            if (DB::table('usuarios')->where('id_persona', $c->id_persona)->exists()) {
                abort(409, 'No se puede eliminar esta cliente porque tiene una cuenta de acceso vinculada.');
            }
            $personaId = $c->id_persona;
            $c->delete();
            Persona::where('id', $personaId)->delete();
        }, 3);

        return response()->noContent();
    }

    public function historial(Request $r, ?int $id = null)
    {
        $id ??= $r->user()->idCliente();
        abort_unless($id, 403);
        Cliente::findOrFail($id);
        $q = ServicioCita::where('estado_servicio', 'realizado')
            ->whereHas('cita', fn ($q) => $q->where('id_cliente', $id))
            ->with([
                'cita' => fn ($q) => $q->select('id', 'id_cliente', 'fecha', 'estado', 'fecha_finalizacion'),
                'empleado.usuario.persona',
                'descuentoManual.motivo',
            ])
            ->orderByDesc('id');

        return Api::pagina($q);
    }

    public function pagos(Request $r)
    {
        $id = $r->user()->idCliente();
        abort_unless($id, 403);
        $q = Cobro::whereHas('cita', fn ($q) => $q->where('id_cliente', $id))
            ->with(['metodo', 'cita:id,fecha,estado'])
            ->orderByDesc('id');

        return Api::pagina($q);
    }

    public function fidelizacion(Request $r, FidelizacionService $f, ?int $id = null)
    {
        $id ??= $r->user()->idCliente();
        abort_unless($id, 403);
        Cliente::findOrFail($id);

        return ['datos' => $f->resumen($id)];
    }
}
