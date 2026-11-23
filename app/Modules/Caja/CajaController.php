<?php

namespace App\Modules\Caja;

use App\Http\Controllers\Controller;
use App\Models\Cobro;
use App\Models\MetodoPago;
use App\Support\Api;
use Illuminate\Http\Request;

class CajaController extends Controller
{
    public function metodosPago()
    {
        return ['datos' => MetodoPago::where('activo', true)->orderBy('id')->get()];
    }

    private function filtrar(Request $r, bool $soloRegistrados = false)
    {
        $d = $r->validate([
            'fecha_desde' => 'nullable|date_format:Y-m-d',
            'fecha_hasta' => 'nullable|date_format:Y-m-d'.($r->filled('fecha_desde') ? '|after_or_equal:fecha_desde' : ''),
            'id_cliente' => 'nullable|integer',
            'id_cita' => 'nullable|integer',
            'id_metodo_pago' => 'nullable|integer',
            'estado' => 'nullable|in:registrado,anulado',
        ]);
        $q = Cobro::query();
        if ($soloRegistrados) {
            $q->where('estado', 'registrado');
        } elseif (isset($d['estado'])) {
            $q->where('estado', $d['estado']);
        }
        foreach (['id_metodo_pago', 'id_cita'] as $f) {
            if (isset($d[$f])) {
                $q->where($f, $d[$f]);
            }
        }
        if (isset($d['id_cliente'])) {
            $q->whereHas('cita', fn ($q) => $q->where('id_cliente', $d['id_cliente']));
        }
        foreach (['fecha_desde' => '>=', 'fecha_hasta' => '<='] as $f => $op) {
            if (isset($d[$f])) {
                $q->whereDate('fecha_cobro', $op, $d[$f]);
            }
        }

        return $q;
    }

    public function cobros(Request $r)
    {
        return Api::pagina($this->filtrar($r)->with(['metodo', 'cita:id,id_cliente,fecha,estado'])->orderByDesc('id'))
            + ['metodos_pago' => MetodoPago::where('activo', true)->orderBy('id')->get()];
    }

    public function cobrar(Request $r, CajaService $caja)
    {
        $d = $r->validate([
            'id_cita' => 'required|integer|exists:citas,id',
            'id_metodo_pago' => 'required|integer|exists:metodos_pago,id',
            'importe' => 'required|numeric|decimal:0,2|between:0.01,9999999999.99',
            'referencia_pago' => 'nullable|string|max:255',
        ]);
        [$cobro, $puntos] = $caja->cobrar($r->user(), $d);

        return response()->json(['datos' => $cobro, 'puntos_otorgados' => $puntos], 201);
    }

    public function anular(Request $r, int $id, CajaService $caja)
    {
        $d = $r->validate(['motivo' => 'required|string|max:2000']);

        return ['datos' => $caja->anular($r->user(), $id, $d['motivo'])];
    }

    public function ingresos(Request $r)
    {
        $q = $this->filtrar($r, true);

        return ['datos' => [
            'total' => number_format((float) (clone $q)->sum('importe'), 2, '.', ''),
            'cantidad' => (clone $q)->count(),
            'por_metodo' => (clone $q)->selectRaw('id_metodo_pago, SUM(importe) as total, COUNT(*) as cantidad')->groupBy('id_metodo_pago')->get(),
            'por_fecha' => (clone $q)->selectRaw('DATE(fecha_cobro) as fecha, SUM(importe) as total')->groupByRaw('DATE(fecha_cobro)')->orderBy('fecha')->get(),
        ]];
    }
}
