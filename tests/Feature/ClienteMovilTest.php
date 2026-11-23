<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Rol;
use App\Modules\Agenda\AgendaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte;
use Tests\TestCase;

class ClienteMovilTest extends TestCase
{
    use RefreshDatabase, Soporte;

    public function test_registro_y_login_movil_conservan_ficha_y_permiten_editar_solo_contacto(): void
    {
        $persona = Persona::create(['dni' => '12345678', 'nombre' => 'Ana', 'apellido_paterno' => 'Pérez', 'telefono' => '111']);
        $cliente = Cliente::create(['id_persona' => $persona->id, 'preferencias' => 'Conservar', 'activo' => true]);
        $datos = [
            'nombre' => 'Ana',
            'apellido_paterno' => 'Pérez',
            'dni' => '12345678',
            'correo' => 'ana@example.test',
            'contrasena' => 'Segura12345',
            'contrasena_confirmacion' => 'Segura12345',
        ];
        $this->postJson('/api/v1/acceso/registro', $datos)->assertCreated()->assertJsonPath('datos.id_cliente', $cliente->id);
        $token = $this->postJson('/api/v1/acceso/token-movil', [
            'correo' => $datos['correo'],
            'contrasena' => $datos['contrasena'],
            'nombre_dispositivo' => 'Android',
        ])->assertCreated()->json('datos.token');

        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'https://movil.invalid')->withToken($token);
        $this->getJson('/api/v1/mi-perfil')->assertOk()->assertJsonPath('datos.id_cliente', $cliente->id);
        $this->patchJson('/api/v1/mi-perfil', [
            'nombre' => 'Ana',
            'apellido_paterno' => 'Actualizada',
            'telefono' => '987654321',
            'correo_contacto' => 'contacto@example.test',
            'preferencias' => 'Horario tarde',
            'dni' => '87654321',
            'correo' => 'otro@example.test',
            'roles' => ['administracion'],
        ])->assertOk()
            ->assertJsonPath('datos.persona.nombre_completo', 'Ana Actualizada')
            ->assertJsonPath('datos.persona.correo_contacto', 'contacto@example.test')
            ->assertJsonPath('datos.persona.dni', '12345678')
            ->assertJsonPath('datos.correo', 'ana@example.test')
            ->assertJsonPath('datos.roles', ['cliente']);

        $this->assertSame('Horario tarde', $cliente->fresh()->preferencias);
        $this->getJson('/api/v1/mi-perfil/historial')->assertOk()->assertJsonCount(0, 'datos');
        $this->getJson('/api/v1/mi-perfil/fidelizacion')->assertOk()->assertJsonPath('datos.nivel.codigo', 'glow');
        $this->deleteJson('/api/v1/acceso/token-movil')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/mi-perfil')->assertUnauthorized();
    }

    public function test_token_movil_no_puede_gestionar_citas_ajenas_ni_modificar_citas_pasadas(): void
    {
        [$cliente, $estilista, $servicios, $datos] = $this->escenario();
        $otro = $this->usuario();
        $agenda = app(AgendaService::class);
        $propia = $agenda->reservar($cliente, $datos + ['id_cliente' => $cliente->idCliente()]);

        $datosAjena = [
            'id_cliente' => $otro->idCliente(),
            'fecha' => $datos['fecha'],
            'servicios' => [
                ['id_servicio' => $servicios[0]->id, 'id_empleado' => $estilista->empleado->id, 'hora_inicio' => '15:00'],
            ],
        ];
        $ajena = $agenda->reservar($otro, $datosAjena);

        $token = $cliente->createToken('Android', ['cliente:movil'])->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withHeader('Origin', 'https://movil.invalid')->withToken($token);
        $fecha = $datos['fecha'];
        $this->getJson('/api/v1/citas')->assertOk()->assertJsonCount(1, 'datos')->assertJsonPath('datos.0.id', $propia->id);
        $this->getJson('/api/v1/citas/'.$ajena->id)->assertForbidden();
        $this->getJson('/api/v1/citas/'.$ajena->id.'/disponibilidad?fecha='.$fecha)->assertForbidden();
        $this->postJson('/api/v1/citas/'.$ajena->id.'/cancelar')->assertForbidden();
        $this->postJson('/api/v1/citas/'.$ajena->id.'/reprogramar', ['fecha' => $fecha])->assertForbidden();
        $this->postJson('/api/v1/citas', $datos + ['id_cliente' => $otro->idCliente()])->assertForbidden();

        $this->travelTo($propia->fechaHoraInicio()->addMinute());
        $this->getJson('/api/v1/citas/'.$propia->id.'/disponibilidad?fecha='.$fecha)->assertConflict();
        $this->postJson('/api/v1/citas/'.$propia->id.'/cancelar')->assertConflict();
        $this->postJson('/api/v1/citas/'.$propia->id.'/reprogramar', ['fecha' => $fecha])->assertConflict();
        $this->assertSame('confirmada', $ajena->fresh()->estado);
        $this->assertSame('confirmada', $propia->fresh()->estado);
    }

    public function test_token_sin_capacidad_expirado_o_cuenta_con_otro_rol_no_autorizan_cambios(): void
    {
        $cliente = $this->usuario();
        $tokenSinCapacidad = $cliente->createToken('Sin capacidad', ['lectura'])->plainTextToken;
        $tokenExpirado = $cliente->createToken('Expirado', ['cliente:movil'], now()->subMinute())->plainTextToken;
        $tokenValido = $cliente->createToken('Android', ['cliente:movil'])->plainTextToken;
        $this->withHeader('Origin', 'https://movil.invalid');
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenSinCapacidad)->patchJson('/api/v1/mi-perfil', ['telefono' => '123'])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenExpirado)->patchJson('/api/v1/mi-perfil', ['telefono' => '123'])->assertUnauthorized();
        $cliente->roles()->attach(Rol::where('codigo', 'recepcion')->value('id'));
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenValido)->patchJson('/api/v1/mi-perfil', ['telefono' => '123'])->assertForbidden();
        $this->assertNull($cliente->persona->fresh()->telefono);
    }
}
