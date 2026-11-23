<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Insumo;
use App\Models\MetodoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte;
use Tests\TestCase;

class CajaInventarioTest extends TestCase
{
    use RefreshDatabase, Soporte;

    public function test_pagos_parciales_puntos_anulacion_y_metodos_yape_plin(): void
    {
        [$u, $e, $ss, $d] = $this->escenario();
        $id = $this->actingAs($u)->postJson('/api/v1/citas', $d)->assertCreated()->json('datos.id');
        $recepcion = $this->usuario('recepcion');

        $metodos = $this->actingAs($recepcion)->getJson('/api/v1/caja/metodos-pago')->assertOk()->json('datos');
        $codigos = array_column($metodos, 'codigo');
        $this->assertContains('yape', $codigos);
        $this->assertContains('plin', $codigos);

        $yapeId = MetodoPago::where('codigo', 'yape')->value('id');
        $efectivoId = MetodoPago::where('codigo', 'efectivo')->value('id');

        // No se puede cobrar antes de completar.
        $this->postJson('/api/v1/caja/cobros', ['id_cita' => $id, 'id_metodo_pago' => $yapeId, 'importe' => '20.00'])->assertConflict();

        $c = Cita::with('servicios')->find($id);
        $this->travelTo($c->fechaHoraFin()->addMinute());
        // 2 servicios realizados a 28.50 cada uno = 57.00 total.
        $resultados = $c->servicios->map(fn ($s, $i) => [
            'id_servicio_cita' => $s->id,
            'estado_servicio' => $i < 2 ? 'realizado' : 'no_realizado',
            'motivo_estado_servicio' => $i < 2 ? null : 'Prueba',
        ])->all();
        $this->actingAs($e)->postJson('/api/v1/citas/'.$id.'/completar', ['servicios' => $resultados])->assertOk();

        // Pago parcial 1: 30.50 -> 30 puntos (floor(30.50/1) - 0).
        $cobro1 = $this->actingAs($recepcion)->postJson('/api/v1/caja/cobros', [
            'id_cita' => $id,
            'id_metodo_pago' => $yapeId,
            'importe' => '30.50',
            'referencia_pago' => 'YAPE-001',
        ])->assertCreated()->assertJsonPath('puntos_otorgados', 30)->json('datos.id');

        // Pago parcial 2: 26.50 -> acumulado 57.00 -> 27 puntos (57 - 30).
        $this->postJson('/api/v1/caja/cobros', [
            'id_cita' => $id,
            'id_metodo_pago' => $efectivoId,
            'importe' => '26.50',
        ])->assertCreated()->assertJsonPath('puntos_otorgados', 27);

        // Exceder el saldo devuelve 422.
        $this->postJson('/api/v1/caja/cobros', [
            'id_cita' => $id,
            'id_metodo_pago' => $efectivoId,
            'importe' => '1.00',
        ])->assertUnprocessable();

        // Cliente ve sus pagos y 57 puntos.
        $this->actingAs($u)->getJson('/api/v1/mi-perfil/pagos')->assertOk()->assertJsonCount(2, 'datos');
        $this->getJson('/api/v1/mi-perfil/fidelizacion')->assertOk()->assertJsonPath('datos.puntos', 57);

        // Anular cobro 1 (30.50): queda 26.50 -> esperado 26 puntos (reversion -30 + ajuste -1).
        $this->actingAs($recepcion)->postJson('/api/v1/caja/cobros/'.$cobro1.'/anular', ['motivo' => 'Error de monto'])->assertOk();
        $this->actingAs($u)->getJson('/api/v1/mi-perfil/fidelizacion')->assertOk()->assertJsonPath('datos.puntos', 26);
    }

    public function test_stock_entero_devolucion_entregas_y_agotamiento(): void
    {
        $admin = $this->usuario('administracion');
        $admin->forceFill(['fecha_confirmacion_mfa' => now()])->save();
        $e = $this->usuario('estilista');
        $this->actingAs($admin)->withSession(['mfa_usuario' => $admin->id]);

        $id = $this->postJson('/api/v1/inventario/insumos', [
            'nombre' => 'Guantes',
            'id_categoria' => 1,
            'existencias_minimas' => 2,
        ])->assertCreated()->json('datos.id');

        $this->postJson('/api/v1/inventario/movimientos', [
            'id_insumo' => $id,
            'tipo_movimiento' => 'entrada',
            'variacion_cantidad' => 10,
            'motivo' => 'Inicial',
        ])->assertCreated();

        $a = $this->postJson('/api/v1/inventario/asignaciones', [
            'id_insumo' => $id,
            'id_empleado' => $e->empleado->id,
            'cantidad_entregada' => 3,
        ])->assertCreated()->json('datos.id');

        $this->postJson('/api/v1/inventario/asignaciones', [
            'id_insumo' => $id,
            'id_empleado' => $e->empleado->id,
            'cantidad_entregada' => 8,
        ])->assertConflict();

        $this->assertSame(7, Insumo::find($id)->existencias_actuales);
        $this->assertDatabaseCount('movimientos_inventario', 2);

        $this->postJson('/api/v1/inventario/movimientos', [
            'id_insumo' => $id,
            'tipo_movimiento' => 'devolucion',
            'variacion_cantidad' => 2,
            'motivo' => 'Devolución de sobrante',
        ])->assertCreated();
        $this->assertSame(9, Insumo::find($id)->existencias_actuales);

        $ajena = $this->usuario('estilista');
        $this->actingAs($ajena)->postJson('/api/v1/inventario/asignaciones/'.$a.'/agotar')->assertForbidden();
        $this->actingAs($e)->postJson('/api/v1/inventario/asignaciones/'.$a.'/agotar')->assertOk();
        $this->getJson('/api/v1/inventario/asignaciones')->assertOk()
            ->assertJsonPath('datos.0.empleado.id', $e->empleado->id)
            ->assertJsonMissingPath('datos.0.empleado.usuario');
        $this->postJson('/api/v1/inventario/asignaciones/'.$a.'/agotar')->assertOk();
        $this->assertSame(9, Insumo::find($id)->existencias_actuales);
    }
}
