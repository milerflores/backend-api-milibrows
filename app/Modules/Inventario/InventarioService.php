<?php

namespace App\Modules\Inventario;

use App\Models\AsignacionInsumo;
use App\Models\Empleado;
use App\Models\Insumo;
use App\Models\MovimientoInventario;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class InventarioService
{
    public function mover(User $u, array $d): MovimientoInventario
    {
        return DB::transaction(function () use ($u, $d) {
            $i = Insumo::lockForUpdate()->findOrFail($d['id_insumo']);
            abort_unless($i->activo, 409, 'Insumo inactivo.');
            $variacion = (int) $d['variacion_cantidad'];
            abort_unless($variacion !== 0, 422, 'La variación debe ser distinta de cero.');
            abort_if(in_array($d['tipo_movimiento'], ['entrada', 'devolucion'], true) && $variacion < 0, 422, 'Una entrada o devolución debe ser positiva.');
            abort_if($d['tipo_movimiento'] === 'salida' && $variacion > 0, 422, 'Una salida debe ser negativa.');

            $antes = (int) $i->existencias_actuales;
            $despues = $antes + $variacion;
            abort_unless($despues >= 0 && $despues <= 2147483647, 409, 'Existencias insuficientes o fuera de rango.');

            $movimiento = MovimientoInventario::create([
                'id_insumo' => $i->id,
                'id_usuario_creador' => $u->id,
                'id_asignacion' => null,
                'tipo_movimiento' => $d['tipo_movimiento'],
                'variacion_cantidad' => $variacion,
                'existencias_antes' => $antes,
                'existencias_despues' => $despues,
                'motivo' => trim($d['motivo']),
                'fecha_creacion' => now(),
            ]);
            $i->update(['existencias_actuales' => $despues]);

            return $movimiento;
        }, 3);
    }

    public function entregar(User $u, array $d): AsignacionInsumo
    {
        return DB::transaction(function () use ($u, $d) {
            $i = Insumo::lockForUpdate()->findOrFail($d['id_insumo']);
            abort_unless($i->activo, 409, 'Insumo inactivo.');
            $e = Empleado::with('usuario.roles')->findOrFail($d['id_empleado']);
            abort_unless($e->activo && $e->usuario->activo && $e->usuario->es('estilista'), 422, 'Estilista no habilitada.');

            $cantidad = (int) $d['cantidad_entregada'];
            $antes = (int) $i->existencias_actuales;
            $despues = $antes - $cantidad;
            abort_if($despues < 0, 409, 'Existencias insuficientes para la entrega.');

            $asignacion = AsignacionInsumo::create([
                'id_insumo' => $i->id,
                'id_empleado' => $e->id,
                'cantidad_entregada' => $cantidad,
                'fecha_entrega' => now(),
                'estado' => 'en_uso',
            ]);
            MovimientoInventario::create([
                'id_insumo' => $i->id,
                'id_usuario_creador' => $u->id,
                'id_asignacion' => $asignacion->id,
                'tipo_movimiento' => 'salida',
                'variacion_cantidad' => -$cantidad,
                'existencias_antes' => $antes,
                'existencias_despues' => $despues,
                'motivo' => 'Entrega a empleado',
                'fecha_creacion' => now(),
            ]);
            $i->update(['existencias_actuales' => $despues]);

            return $asignacion;
        }, 3);
    }

    public function agotar(User $u, int $id): AsignacionInsumo
    {
        return DB::transaction(function () use ($u, $id) {
            $a = AsignacionInsumo::lockForUpdate()->findOrFail($id);
            abort_unless($u->es('administracion') || $u->empleado?->id === $a->id_empleado, 403);
            if ($a->estado !== 'agotado') {
                $a->update(['estado' => 'agotado', 'fecha_agotamiento' => now()]);
            }

            return $a;
        }, 3);
    }
}
