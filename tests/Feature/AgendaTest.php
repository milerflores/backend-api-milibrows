<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\JornadaEmpleado;
use App\Models\MotivoDescuento;
use App\Models\Servicio;
use App\Modules\Agenda\AgendaService;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte;
use Tests\TestCase;

class AgendaTest extends TestCase
{
    use RefreshDatabase, Soporte;

    public function test_reserva_multi_especialista_y_descuento_automatico_glow(): void
    {
        [$cliente, $estilista1, $servicios, $datos] = $this->escenario();
        $estilista2 = $this->usuario('estilista');
        foreach (range(1, 6) as $dia) {
            JornadaEmpleado::create(['id_empleado' => $estilista2->empleado->id, 'dia_semana' => $dia, 'hora_inicio' => '10:00', 'hora_fin' => '22:00']);
        }
        $estilista2->empleado->servicios()->attach($servicios->pluck('id')->all(), ['activo' => true]);

        $payload = [
            'fecha' => $datos['fecha'],
            'servicios' => [
                ['id_servicio' => $servicios[0]->id, 'id_empleado' => $estilista1->empleado->id, 'hora_inicio' => '12:00'],
                ['id_servicio' => $servicios[1]->id, 'id_empleado' => $estilista2->empleado->id, 'hora_inicio' => '12:00'],
            ],
        ];
        $res = $this->actingAs($cliente)->postJson('/api/v1/citas', $payload)->assertCreated();
        // Nivel Glow (5%) sobre S/ 30.00 -> descuento 1.50, acordado 28.50.
        $res->assertJsonPath('datos.servicios.0.precio_lista', '30.00')
            ->assertJsonPath('datos.servicios.0.monto_descuento_automatico', '1.50')
            ->assertJsonPath('datos.servicios.0.precio_acordado', '28.50');
        $this->actingAs($estilista1)->getJson('/api/v1/citas')->assertOk()->assertJsonCount(1, 'datos');
        $this->actingAs($estilista2)->getJson('/api/v1/citas')->assertOk()->assertJsonCount(1, 'datos');
    }

    public function test_disponibilidad_reprogramacion_excluye_su_cita_y_conserva_precio_acordado(): void
    {
        [$u, $estilista, $servicios, $datos] = $this->escenario();
        $id = $this->actingAs($u)->postJson('/api/v1/citas', $datos)->assertCreated()->json('datos.id');
        $segunda = [
            'fecha' => $datos['fecha'],
            'servicios' => [
                ['id_servicio' => $servicios[0]->id, 'id_empleado' => $estilista->empleado->id, 'hora_inicio' => '13:30'],
                ['id_servicio' => $servicios[1]->id, 'id_empleado' => $estilista->empleado->id, 'hora_inicio' => '14:00'],
                ['id_servicio' => $servicios[2]->id, 'id_empleado' => $estilista->empleado->id, 'hora_inicio' => '14:30'],
            ],
        ];
        $this->postJson('/api/v1/citas', $segunda)->assertCreated();
        Servicio::whereIn('id', $servicios->pluck('id'))->update(['duracion_minutos' => 120, 'precio' => 99, 'activo' => false, 'visible_al_publico' => false]);

        $resultado = $this->getJson('/api/v1/citas/'.$id.'/disponibilidad?fecha='.$datos['fecha'])
            ->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.duracion_minutos', 90)->json('datos.0');
        $this->assertSame($estilista->empleado->id, $resultado['id_empleado']);
        $this->assertSame('10:00', $resultado['intervalos'][0]['hora_inicio']);
        $this->assertSame('13:30', $resultado['intervalos'][0]['hora_fin']);

        $c = Cita::with('servicios')->find($id);
        $choca = [
            'fecha' => $datos['fecha'],
            'servicios' => $c->servicios->map(fn ($s, $i) => [
                'id_servicio_cita' => $s->id,
                'hora_inicio' => CarbonImmutable::createFromFormat('H:i', '12:30')->addMinutes($i * 30)->format('H:i'),
            ])->all(),
        ];
        $this->postJson('/api/v1/citas/'.$id.'/reprogramar', $choca)->assertConflict();
        $this->postJson('/api/v1/citas/'.$id.'/reprogramar', ['fecha' => $datos['fecha']])
            ->assertOk()->assertJsonPath('datos.servicios.0.duracion_minutos', 30)->assertJsonPath('datos.servicios.0.precio_acordado', '28.50');
    }

    public function test_disponibilidad_reprogramacion_respeta_roles_propiedad_y_estado(): void
    {
        [$cliente, $estilista, $servicios, $datos] = $this->escenario();
        $id = $this->actingAs($cliente)->postJson('/api/v1/citas', $datos)->assertCreated()->json('datos.id');
        $url = '/api/v1/citas/'.$id.'/disponibilidad?fecha='.$datos['fecha'];
        $this->actingAs($this->usuario())->getJson($url)->assertForbidden();
        $this->actingAs($estilista)->getJson($url)->assertForbidden();
        $this->actingAs($this->usuario('recepcion'))->getJson($url)->assertOk();
        $admin = $this->usuario('administracion');
        $this->actingAs($admin)->getJson($url)->assertForbidden();
        $admin->forceFill(['fecha_confirmacion_mfa' => now()])->save();
        $this->withSession(['mfa_usuario' => $admin->id])->getJson($url)->assertOk();
        $this->actingAs($cliente)->getJson('/api/v1/citas/'.$id.'/disponibilidad?fecha=2000-01-01')->assertUnprocessable();
        $this->travelTo(Cita::find($id)->fechaHoraInicio()->addMinute());
        $this->getJson($url)->assertConflict();
        $this->actingAs($this->usuario('recepcion'))->postJson('/api/v1/citas/'.$id.'/cancelar', ['motivo' => 'Cambio de plan'])->assertOk();
        $this->getJson($url)->assertConflict();
    }

    public function test_tramos_contiguos_se_unen_y_una_pausa_real_impide_reservar(): void
    {
        [$u, $estilista, $ss, $d] = $this->escenario();
        $e = $estilista->empleado;
        $e->jornada()->where('dia_semana', 1)->delete();
        foreach ([['10:00', '12:00'], ['12:00', '16:00']] as [$desde, $hasta]) {
            $e->jornada()->create(['dia_semana' => 1, 'hora_inicio' => $desde, 'hora_fin' => $hasta]);
        }
        $d['servicios'] = [
            ['id_servicio' => $ss[0]->id, 'id_empleado' => $e->id, 'hora_inicio' => '11:30'],
            ['id_servicio' => $ss[1]->id, 'id_empleado' => $e->id, 'hora_inicio' => '12:00'],
            ['id_servicio' => $ss[2]->id, 'id_empleado' => $e->id, 'hora_inicio' => '12:30'],
        ];
        $id = $this->actingAs($u)->postJson('/api/v1/citas', $d)->assertCreated()->json('datos.id');
        $this->postJson('/api/v1/citas/'.$id.'/cancelar')->assertOk();
        $e->jornada()->where('dia_semana', 1)->where('hora_inicio', '12:00')->update(['hora_inicio' => '12:15']);
        $this->postJson('/api/v1/citas', $d)->assertConflict();
    }

    public function test_autorizacion_se_revalida_dentro_de_la_transaccion(): void
    {
        [$u, $estilista, $ss, $d] = $this->escenario();
        $id = $this->actingAs($u)->postJson('/api/v1/citas', $d)->assertCreated()->json('datos.id');
        $otro = $this->usuario('estilista');
        $otro->empleado->servicios()->attach($ss->pluck('id')->all(), ['activo' => true]);
        $c = Cita::with('servicios')->find($id);
        $this->assertTrue($estilista->can('update', $c));
        $c->servicios()->update(['id_empleado' => $otro->empleado->id]);
        $this->expectException(AuthorizationException::class);
        app(AgendaService::class)->cambiar($estilista, $id, 'cancelar', ['motivo' => 'Prueba']);
    }

    public function test_atencion_parcial_descuento_manual_y_historial(): void
    {
        [$u, $e, $ss, $d] = $this->escenario();
        $id = $this->actingAs($u)->postJson('/api/v1/citas', $d)->assertCreated()->json('datos.id');
        $c = Cita::with('servicios')->find($id);
        $recepcion = $this->usuario('recepcion');
        $motivo = MotivoDescuento::create(['nombre' => 'Cortesía', 'activo' => true]);

        // Descuento manual de S/ 3.50 sobre el primer servicio (28.50 -> 25.00).
        $sc0 = $c->servicios->first();
        $this->actingAs($recepcion)
            ->putJson("/api/v1/citas/{$id}/servicios/{$sc0->id}/descuento-manual", ['monto' => '3.50', 'id_motivo_descuento' => $motivo->id])
            ->assertOk()
            ->assertJsonPath('datos.servicios.0.precio_acordado', '25.00');

        $this->travelTo($c->fechaHoraFin()->addMinute());
        $resultados = $c->servicios->map(fn ($s, $i) => [
            'id_servicio_cita' => $s->id,
            'estado_servicio' => $i < 2 ? 'realizado' : 'no_realizado',
            'motivo_estado_servicio' => $i < 2 ? null : 'Cliente decidió omitirlo',
        ])->all();
        $this->actingAs($e)->postJson('/api/v1/citas/'.$id.'/completar', ['servicios' => $resultados])->assertOk();
        $this->postJson('/api/v1/citas/'.$id.'/completar', ['servicios' => $resultados])->assertConflict();
        $this->assertDatabaseCount('cobros', 0);
        // Sin cobro todavía: 0 puntos, nivel Glow, 1 visita, 2 servicios realizados.
        $this->actingAs($u)->getJson('/api/v1/mi-perfil/fidelizacion')
            ->assertOk()
            ->assertJsonPath('datos.puntos', 0)
            ->assertJsonPath('datos.visitas', 1)
            ->assertJsonPath('datos.servicios_realizados', 2)
            ->assertJsonPath('datos.nivel.codigo', 'glow');
        $this->getJson('/api/v1/mi-perfil/historial')->assertOk()->assertJsonCount(2, 'datos');
    }

    public function test_token_movil_permite_reservar_y_gestionar_sus_citas_y_contacto(): void
    {
        [$u, $e, $ss, $d] = $this->escenario();
        $token = $u->createToken('Android', ['cliente:movil'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'https://movil.invalid');
        $this->withToken($token)->postJson('/api/v1/citas', $d)->assertCreated();
        $c = Cita::with('servicios')->first();
        $reprog = [
            'fecha' => $c->fecha->addDay()->format('Y-m-d'),
        ];
        $this->postJson('/api/v1/citas/'.$c->id.'/reprogramar', $reprog)
            ->assertOk()->assertJsonPath('datos.servicios.0.precio_acordado', '28.50');
        $this->patchJson('/api/v1/mi-perfil', ['nombre' => 'Ana', 'apellido_paterno' => 'Móvil', 'telefono' => '987654321', 'preferencias' => 'Por la tarde'])
            ->assertOk()->assertJsonPath('datos.persona.telefono', '987654321');
        $this->assertSame('Por la tarde', $u->persona->cliente->fresh()->preferencias);
        $this->postJson('/api/v1/citas/'.$c->id.'/completar', [])->assertForbidden();
        $this->postJson('/api/v1/citas/'.$c->id.'/marcar-inasistencia', [])->assertForbidden();
        $this->getJson('/api/v1/administracion/servicios')->assertForbidden();
        $this->postJson('/api/v1/citas/'.$c->id.'/cancelar', ['motivo' => 'Cambio de planes'])
            ->assertOk()->assertJsonPath('datos.estado', 'cancelada');
        $this->postJson('/api/v1/citas/'.$c->id.'/reprogramar', $reprog)->assertConflict();
    }
}
