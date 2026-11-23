<?php

namespace App\Modules\Fidelizacion;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionEstudio;
use App\Models\MotivoDescuento;
use App\Models\MovimientoPuntos;
use App\Models\NivelFidelizacion;
use App\Support\Api;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FidelizacionController extends Controller
{
    public function niveles()
    {
        return [
            'datos' => NivelFidelizacion::orderBy('orden')->get(),
            'monto_por_punto' => ConfiguracionEstudio::findOrFail(1)->monto_por_punto,
        ];
    }

    public function guardarNiveles(Request $r)
    {
        $d = $r->validate([
            'monto_por_punto' => 'sometimes|required|numeric|decimal:0,2|gt:0|max:9999999999.99',
            'niveles' => 'required|array|size:3',
            'niveles.*' => 'array:codigo,nombre,puntos_minimos,porcentaje_descuento,activo',
            'niveles.*.codigo' => 'required|in:glow,golden,vip|distinct',
            'niveles.*.nombre' => 'required|string|max:255',
            'niveles.*.puntos_minimos' => 'required|integer|min:0|max:1000000000',
            'niveles.*.porcentaje_descuento' => 'required|numeric|decimal:0,2|between:0,100',
            'niveles.*.activo' => 'required|boolean',
        ]);

        $niveles = collect($d['niveles'])->keyBy('codigo');
        $glow = $niveles['glow'];
        $golden = $niveles['golden'];
        $vip = $niveles['vip'];

        abort_unless((int) $glow['puntos_minimos'] === 0 && (bool) $glow['activo'] === true, 422, 'Glow es el nivel inicial, debe permanecer activo y con 0 puntos mínimos.');
        foreach ([$golden, $vip] as $n) {
            abort_if((bool) $n['activo'] && (int) $n['puntos_minimos'] <= 0, 422, 'Los niveles Golden y VIP activos requieren un mínimo de puntos mayor a cero.');
        }
        if ((int) $golden['puntos_minimos'] > 0 && (int) $vip['puntos_minimos'] > 0) {
            abort_unless((int) $vip['puntos_minimos'] > (int) $golden['puntos_minimos'], 422, 'VIP debe requerir más puntos mínimos que Golden.');
        }

        DB::transaction(function () use ($d, $niveles) {
            $cfg = ConfiguracionEstudio::lockForUpdate()->findOrFail(1);
            if (isset($d['monto_por_punto'])) {
                $cfg->update(['monto_por_punto' => $d['monto_por_punto']]);
            }
            foreach ($niveles as $codigo => $n) {
                NivelFidelizacion::where('codigo', $codigo)->update([
                    'nombre' => $n['nombre'],
                    'puntos_minimos' => $n['puntos_minimos'],
                    'porcentaje_descuento' => $n['porcentaje_descuento'],
                    'activo' => $n['activo'],
                ]);
            }
        });

        return $this->niveles();
    }

    public function motivosDescuento(Request $r)
    {
        $r->validate(['solo_activos' => 'sometimes|boolean']);
        $q = MotivoDescuento::query()
            ->when($r->boolean('solo_activos'), fn ($q) => $q->where('activo', true))
            ->orderBy('nombre');

        return ['datos' => $q->get()];
    }

    public function guardarMotivoDescuento(Request $r, ?int $id = null)
    {
        $p = $id ? 'sometimes' : 'required';
        $d = $r->validate([
            'nombre' => "$p|required|string|max:255",
            'activo' => 'sometimes|boolean',
        ]);
        $m = $id ? MotivoDescuento::findOrFail($id) : new MotivoDescuento;
        $m->fill($d)->save();

        return response()->json(['datos' => $m->fresh()], $id ? 200 : 201);
    }

    public function movimientosPuntos(Request $r)
    {
        $r->validate([
            'id_cliente' => 'nullable|integer',
            'tipo' => 'nullable|in:ganado,reversion,ajuste',
        ]);
        $q = MovimientoPuntos::with(['cliente.persona', 'cobro:id,id_cita,importe,estado'])
            ->when($r->id_cliente, fn ($q) => $q->where('id_cliente', $r->id_cliente))
            ->when($r->tipo, fn ($q) => $q->where('tipo', $r->tipo))
            ->orderByDesc('id');

        return Api::pagina($q);
    }

    public function ajustarPuntos(Request $r, FidelizacionService $fidelizacion)
    {
        $d = $r->validate([
            'id_cliente' => 'required|integer|exists:clientes,id',
            'puntos' => 'required|integer|not_in:0|between:-1000000,1000000',
            'motivo' => 'required|string|max:2000',
        ]);
        $m = $fidelizacion->ajustar($r->user(), $d);

        return response()->json(['datos' => $m], 201);
    }
}
