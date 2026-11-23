<?php

namespace App\Modules\Caja;

use App\Models\Cita;
use App\Models\Cobro;
use App\Models\ConfiguracionEstudio;
use App\Models\MetodoPago;
use App\Models\MovimientoPuntos;
use App\Models\User;
use App\Support\Api;
use Illuminate\Support\Facades\DB;

class CajaService
{
    public function cobrar(User $u, array $d): array
    {
        return DB::transaction(function () use ($u, $d) {
            $cita = Cita::lockForUpdate()->findOrFail($d['id_cita']);
            abort_unless($cita->estado === 'completada', 409, 'Solo se puede cobrar una atención completada.');
            abort_unless(MetodoPago::where('id', $d['id_metodo_pago'])->where('activo', true)->exists(), 422, 'Método de pago inactivo.');

            $pagado = (float) Cobro::where('id_cita', $cita->id)
                ->where('estado', 'registrado')
                ->lockForUpdate()
                ->sum('importe');
            $total = (float) $cita->servicios()
                ->where('estado_servicio', 'realizado')
                ->sum('precio_acordado');

            $importeCent = Api::unidades($d['importe']);
            $pagadoCent = Api::unidades($pagado);
            $totalCent = Api::unidades($total);
            abort_unless($importeCent > 0, 422, 'El importe debe ser mayor a cero.');
            abort_unless($totalCent > 0 && ($pagadoCent + $importeCent) <= $totalCent, 422, 'El pago excede el saldo pendiente de la cita.');

            $tasaCent = Api::unidades(ConfiguracionEstudio::findOrFail(1)->monto_por_punto);
            $cobro = Cobro::create([
                'id_cita' => $cita->id,
                'id_metodo_pago' => $d['id_metodo_pago'],
                'id_usuario_creador' => $u->id,
                'importe' => number_format($importeCent / 100, 2, '.', ''),
                'fecha_cobro' => now(),
                'referencia_pago' => isset($d['referencia_pago']) && trim((string) $d['referencia_pago']) !== ''
                    ? trim((string) $d['referencia_pago'])
                    : null,
                'estado' => 'registrado',
            ]);

            $puntos = intdiv($pagadoCent + $importeCent, $tasaCent) - intdiv($pagadoCent, $tasaCent);
            MovimientoPuntos::create([
                'id_cliente' => $cita->id_cliente,
                'id_cobro' => $cobro->id,
                'id_usuario_creador' => $u->id,
                'tipo' => 'ganado',
                'puntos' => $puntos,
                'motivo' => null,
                'fecha_creacion' => now(),
            ]);

            return [$cobro->load('metodo'), $puntos];
        }, 3);
    }

    public function anular(User $u, int $idCobro, string $motivo): Cobro
    {
        $motivo = trim($motivo);
        abort_if($motivo === '', 422, 'Indique el motivo de la anulación.');

        return DB::transaction(function () use ($u, $idCobro, $motivo) {
            $cobro = Cobro::lockForUpdate()->findOrFail($idCobro);
            $cita = Cita::lockForUpdate()->findOrFail($cobro->id_cita);
            abort_unless($cobro->estado === 'registrado', 409, 'El cobro ya fue anulado.');

            $cobro->update(['estado' => 'anulado']);

            $ganado = MovimientoPuntos::where('id_cobro', $cobro->id)
                ->where('tipo', 'ganado')
                ->first();

            if ($ganado) {
                MovimientoPuntos::create([
                    'id_cliente' => $cita->id_cliente,
                    'id_cobro' => $cobro->id,
                    'id_usuario_creador' => $u->id,
                    'tipo' => 'reversion',
                    'puntos' => -$ganado->puntos,
                    'motivo' => $motivo,
                    'fecha_creacion' => now(),
                ]);

                $pagadoRestanteCent = Api::unidades(
                    (float) Cobro::where('id_cita', $cita->id)->where('estado', 'registrado')->lockForUpdate()->sum('importe')
                );
                $tasaCent = Api::unidades(ConfiguracionEstudio::findOrFail(1)->monto_por_punto);
                $netoCita = (int) MovimientoPuntos::whereIn('id_cobro', Cobro::select('id')->where('id_cita', $cita->id))->sum('puntos');
                $esperado = intdiv($pagadoRestanteCent, $tasaCent);
                $ajuste = $esperado - $netoCita;

                if ($ajuste !== 0) {
                    MovimientoPuntos::create([
                        'id_cliente' => $cita->id_cliente,
                        'id_cobro' => $cobro->id,
                        'id_usuario_creador' => $u->id,
                        'tipo' => 'ajuste',
                        'puntos' => $ajuste,
                        'motivo' => 'Reajuste del redondeo acumulado al anular un pago parcial',
                        'fecha_creacion' => now(),
                    ]);
                }
            }

            return $cobro->fresh('metodo');
        }, 3);
    }
}
