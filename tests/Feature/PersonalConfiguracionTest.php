<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Empleado;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte;
use Tests\TestCase;

class PersonalConfiguracionTest extends TestCase
{
    use RefreshDatabase, Soporte;

    private function admin(): User
    {
        $u = $this->usuario('administracion');
        $u->forceFill(['fecha_confirmacion_mfa' => now()])->save();
        $this->actingAs($u)->withSession(['mfa_usuario' => $u->id]);

        return $u;
    }

    public function test_admin_gestiona_personal_y_recepcion_no_puede(): void
    {
        $this->admin();
        $d = [
            'nombre' => 'Estilista',
            'apellido_paterno' => 'Prueba',
            'correo' => 'personal@example.test',
            'rol' => 'estilista',
            'cargo' => 'Estilista',
            'contrasena' => 'InicialSegura123',
            'admite_reservas' => true,
        ];
        $id = $this->postJson('/api/v1/empleados', $d)->assertCreated()->json('datos.id');
        $this->getJson('/api/v1/empleados/'.$id)->assertOk()->assertJsonPath('datos.cuenta.correo', $d['correo']);
        $this->putJson('/api/v1/empleados/'.$id.'/jornada', ['jornada' => [['dia_semana' => 1, 'hora_inicio' => '10:00', 'hora_fin' => '14:00']]])->assertOk();
        $this->putJson('/api/v1/empleados/'.$id.'/jornada', ['jornada' => [['dia_semana' => 1, 'hora_inicio' => '14:00', 'hora_fin' => '10:00']]])->assertUnprocessable();
        $this->patchJson('/api/v1/empleados/'.$id.'/cuenta', ['correo' => 'nuevo@example.test', 'activo' => false])->assertOk();
        $this->putJson('/api/v1/empleados/'.$id.'/cuenta/contrasena', ['contrasena' => 'CambioSeguro123'])->assertNoContent();
        $u = Empleado::find($id)->usuario;
        $this->assertSame(3, (int) $u->version_sesion);

        $recepcion = $this->usuario('recepcion');
        $this->actingAs($recepcion)->postJson('/api/v1/empleados', $d)->assertForbidden();
        $this->getJson('/api/v1/administracion/resumen')->assertForbidden();
        $this->postJson('/api/v1/clientes', ['nombre' => 'Sin', 'apellido_paterno' => 'Cuenta', 'dni' => '87654321'])->assertCreated();
    }

    public function test_configuracion_no_invalida_citas_y_niveles_son_editables(): void
    {
        [$u, $e, $ss, $d] = $this->escenario();
        $id = $this->actingAs($u)->postJson('/api/v1/citas', $d)->assertCreated()->json('datos.id');
        $this->admin();
        $this->putJson('/api/v1/configuracion/horario-estudio', ['horario' => []])->assertConflict();
        $this->putJson('/api/v1/empleados/'.$e->empleado->id.'/servicios', ['ids_servicios' => []])->assertConflict();
        $this->putJson('/api/v1/empleados/'.$e->empleado->id.'/jornada', ['jornada' => []])->assertConflict();
        $this->patchJson('/api/v1/administracion/servicios/'.$ss[0]->id, ['precio' => 50])->assertOk();
        $this->assertSame('28.50', Cita::find($id)->servicios->first()->precio_acordado);

        $niveles = [
            ['codigo' => 'glow', 'nombre' => 'Glow', 'puntos_minimos' => 0, 'porcentaje_descuento' => '5.00', 'activo' => true],
            ['codigo' => 'golden', 'nombre' => 'Golden', 'puntos_minimos' => 100, 'porcentaje_descuento' => '15.00', 'activo' => true],
            ['codigo' => 'vip', 'nombre' => 'VIP', 'puntos_minimos' => 500, 'porcentaje_descuento' => '25.00', 'activo' => true],
        ];
        $this->putJson('/api/v1/fidelizacion/niveles', ['monto_por_punto' => '2.00', 'niveles' => $niveles])
            ->assertOk()
            ->assertJsonPath('monto_por_punto', '2.00');
        $this->postJson('/api/v1/fidelizacion/puntos/ajustes', [
            'id_cliente' => $u->idCliente(),
            'puntos' => 120,
            'motivo' => 'Bono de bienvenida',
        ])->assertCreated();
        $this->getJson('/api/v1/clientes/'.$u->idCliente().'/fidelizacion')
            ->assertOk()
            ->assertJsonPath('datos.puntos', 120)
            ->assertJsonPath('datos.nivel.codigo', 'golden');
        $this->putJson('/api/v1/configuracion/notificaciones', ['canal_notificacion' => 'correo', 'anticipacion_recordatorio_min' => 60])->assertOk();
        $this->getJson('/api/v1/administracion/resumen')->assertOk();
    }
}
