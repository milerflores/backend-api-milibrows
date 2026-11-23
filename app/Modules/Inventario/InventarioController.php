<?php

namespace App\Modules\Inventario;

use App\Http\Controllers\Controller;
use App\Models\AsignacionInsumo;
use App\Models\CategoriaInventario;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Support\Api;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function insumos(Request $r)
    {
        $r->validate(['requiere_reposicion' => 'sometimes|boolean']);

        return Api::pagina(
            Insumo::with('categoria')
                ->when($r->boolean('requiere_reposicion'), fn ($q) => $q->whereColumn('existencias_actuales', '<=', 'existencias_minimas'))
                ->orderBy('nombre')
        ) + ['categorias' => CategoriaInventario::orderBy('nombre')->get()];
    }

    public function detalle(int $id)
    {
        return ['datos' => Insumo::with('categoria')->findOrFail($id)];
    }

    public function guardarInsumo(Request $r, ?int $id = null)
    {
        $p = $id ? 'sometimes' : 'required';
        $d = $r->validate([
            'nombre' => "$p|required|string|max:255",
            'id_categoria' => "$p|integer|exists:categorias_inventario,id",
            'existencias_minimas' => 'sometimes|integer|min:0|max:2147483647',
            'activo' => 'sometimes|boolean',
            'existencias_actuales' => 'prohibited',
        ]);
        $i = $id ? Insumo::findOrFail($id) : new Insumo;
        $i->fill($d)->save();

        return response()->json(['datos' => $i->fresh('categoria')], $id ? 200 : 201);
    }

    public function movimientos(Request $r)
    {
        $r->validate([
            'id_insumo' => 'nullable|integer',
            'tipo_movimiento' => 'nullable|in:entrada,salida,devolucion,ajuste',
        ]);

        return Api::pagina(
            MovimientoInventario::with('insumo:id,nombre')
                ->when($r->id_insumo, fn ($q) => $q->where('id_insumo', $r->id_insumo))
                ->when($r->tipo_movimiento, fn ($q) => $q->where('tipo_movimiento', $r->tipo_movimiento))
                ->orderByDesc('id')
        );
    }

    public function mover(Request $r, InventarioService $inventario)
    {
        $d = $r->validate([
            'id_insumo' => 'required|integer|exists:insumos,id',
            'tipo_movimiento' => 'required|in:entrada,salida,devolucion,ajuste',
            'variacion_cantidad' => 'required|integer|not_in:0|between:-1000000000,1000000000',
            'motivo' => 'required|string|max:2000',
        ]);
        $m = $inventario->mover($r->user(), $d);

        return response()->json(['datos' => $m], 201);
    }

    public function asignaciones(Request $r)
    {
        $r->validate([
            'id_empleado' => 'nullable|integer',
            'estado' => 'nullable|in:en_uso,agotado',
        ]);
        $q = AsignacionInsumo::with(['insumo', 'empleado.usuario.persona']);
        if (! $r->user()->es('administracion')) {
            $q->where('id_empleado', $r->user()->empleado?->id ?? 0);
        }

        $pagina = Api::pagina(
            $q->when($r->id_empleado, fn ($q) => $q->where('id_empleado', $r->id_empleado))
                ->when($r->estado, fn ($q) => $q->where('estado', $r->estado))
                ->orderByDesc('id')
        );
        $pagina['datos'] = collect($pagina['datos'])->map(fn ($a) => $a->attributesToArray() + [
            'insumo' => $a->insumo,
            'empleado' => [
                'id' => $a->id_empleado,
                'nombre_completo' => $a->empleado->usuario->persona->nombre_completo,
            ],
        ]);

        return $pagina;
    }

    public function asignar(Request $r, InventarioService $inventario)
    {
        $d = $r->validate([
            'id_insumo' => 'required|integer|exists:insumos,id',
            'id_empleado' => 'required|integer|exists:empleados,id',
            'cantidad_entregada' => 'required|integer|min:1|max:1000000000',
        ]);
        $a = $inventario->entregar($r->user(), $d);

        return response()->json(['datos' => $a->load('insumo')], 201);
    }

    public function agotar(Request $r, int $id, InventarioService $inventario)
    {
        return ['datos' => $inventario->agotar($r->user(), $id)];
    }
}
