<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\Soporte;
use Tests\TestCase;

class EsquemaEspanolTest extends TestCase
{
    use RefreshDatabase, Soporte;

    public function test_esquema_vigente_y_json_del_negocio_usan_espanol_sin_exponer_secretos(): void
    {
        foreach (['usuarios', 'personas', 'clientes', 'empleados', 'servicios', 'citas', 'insumos'] as $tabla) {
            $this->assertTrue(Schema::hasColumns($tabla, ['fecha_creacion', 'fecha_actualizacion']));
            $this->assertFalse(Schema::hasColumn($tabla, 'created_at'));
            $this->assertFalse(Schema::hasColumn($tabla, 'updated_at'));
        }
        foreach (['historial_cita', 'movimientos_puntos', 'motivos_descuento', 'descuentos_manuales_cita'] as $nueva) {
            $this->assertTrue(Schema::hasTable($nueva));
        }
        foreach (['cajas', 'turnos_caja', 'unidades_medida', 'categorias_servicios', 'especialidades', 'programas_sellos', 'intentos_notificacion'] as $eliminada) {
            $this->assertFalse(Schema::hasTable($eliminada));
        }

        $usuario = User::factory()->create(['secreto_segundo_factor' => 'oculto', 'codigos_respaldo_mfa' => 'ocultos']);
        $json = $usuario->toArray();
        $this->assertArrayHasKey('correo', $json);
        $this->assertArrayHasKey('fecha_creacion', $json);
        foreach (['contrasena', 'secreto_segundo_factor', 'codigos_respaldo_mfa'] as $campo) {
            $this->assertArrayNotHasKey($campo, $json);
        }
        $usuario->forceFill(['fecha_verificacion_correo' => null])->save();
        $this->assertFalse($usuario->hasVerifiedEmail());
        $this->assertTrue($usuario->markEmailAsVerified());
        $this->assertTrue($usuario->fresh()->hasVerifiedEmail());
        $this->assertSame($usuario->correo, $usuario->getEmailForVerification());
        $this->assertTrue(Schema::hasColumns('sessions', ['user_id', 'last_activity']));
        $this->assertTrue(Schema::hasColumns('personal_access_tokens', ['tokenable_id', 'abilities', 'expires_at']));
    }
}
