<?php

namespace Tests\Feature;

use App\Models\Cliente;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_registro_vincula_ficha_y_token_solo_cliente(): void
    {
        $p = Persona::create(['dni' => '12345678', 'nombre' => 'Ana', 'apellido_paterno' => 'García']);
        $c = Cliente::create(['id_persona' => $p->id, 'preferencias' => 'Conservar', 'activo' => true]);
        $this->postJson('/api/v1/acceso/registro', [
            'dni' => '12345678',
            'nombre' => 'Ana',
            'apellido_paterno' => 'García',
            'correo' => 'ana@example.test',
            'contrasena' => 'Segura12345',
            'contrasena_confirmacion' => 'Segura12345',
        ])->assertCreated()->assertJsonPath('datos.id_cliente', $c->id);
        $this->assertSame('Conservar', $c->fresh()->preferencias);

        $this->postJson('/api/v1/acceso/token-movil', [
            'correo' => 'ana@example.test',
            'contrasena' => 'Segura12345',
            'nombre_dispositivo' => 'Android',
        ])->assertCreated();

        $u = User::first();
        $u->roles()->sync([Rol::where('codigo', 'administracion')->value('id')]);
        $this->postJson('/api/v1/acceso/token-movil', [
            'correo' => 'ana@example.test',
            'contrasena' => 'Segura12345',
            'nombre_dispositivo' => 'Android',
        ])->assertForbidden();
    }

    public function test_activacion_confirmacion_y_recuperacion_son_de_un_solo_uso(): void
    {
        $u = User::factory()->create();
        $u->roles()->attach(Rol::where('codigo', 'administracion')->value('id'));
        $this->actingAs($u)->postJson('/api/v1/acceso/segundo-factor/activar', ['contrasena' => 'password'])->assertOk();
        $u->refresh();
        $code = (new Google2FA)->getCurrentOtp(Fortify::currentEncrypter()->decrypt($u->secreto_segundo_factor));
        $codes = $this->postJson('/api/v1/acceso/segundo-factor/confirmar', ['codigo' => $code])->assertOk()->json('datos.codigos_recuperacion');
        $this->assertCount(8, $codes);
        $this->postJson('/api/v1/acceso/segundo-factor/verificar', ['codigo_recuperacion' => $codes[0]])->assertOk();
        $this->postJson('/api/v1/acceso/segundo-factor/verificar', ['codigo_recuperacion' => $codes[0]])->assertUnprocessable();
    }

    public function test_restablecimiento_revoca_tokens_y_no_elimina_mfa(): void
    {
        Notification::fake();
        $u = User::factory()->create(['secreto_segundo_factor' => 'cifrado', 'fecha_confirmacion_mfa' => now()]);
        $u->createToken('prueba');
        $token = Password::createToken($u);
        $data = ['correo' => $u->correo, 'token' => $token, 'contrasena' => 'NuevaSegura123', 'contrasena_confirmacion' => 'NuevaSegura123'];
        $this->postJson('/api/v1/acceso/restablecer-contrasena', $data)->assertOk();
        $this->assertTrue(Hash::check('NuevaSegura123', $u->fresh()->contrasena));
        $this->assertNotNull($u->fresh()->fecha_confirmacion_mfa);
        $this->assertSame(2, (int) $u->fresh()->version_sesion);
        $this->assertSame(0, $u->tokens()->count());
        $this->postJson('/api/v1/acceso/restablecer-contrasena', $data)->assertUnprocessable();
    }

    public function test_enlace_de_recuperacion_conserva_hash_caducidad_y_limite_de_envios(): void
    {
        Notification::fake();
        $u = User::factory()->create();
        $this->postJson('/api/v1/acceso/solicitar-restablecimiento', ['correo' => $u->correo])->assertOk();
        Notification::assertSentTo($u, ResetPassword::class, function ($aviso) use ($u) {
            $registro = DB::table('codigos_restablecimiento')->where('correo', $u->correo)->first();
            $this->assertTrue(Hash::check($aviso->token, $registro->codigo));
            $this->assertSame($u->correo, $u->routeNotificationFor('mail', $aviso));
            $this->assertStringContainsString(http_build_query(['token' => $aviso->token, 'correo' => $u->correo]), $aviso->toMail($u)->actionUrl);
            $this->assertTrue(Password::tokenExists($u, $aviso->token));

            DB::table('codigos_restablecimiento')->where('correo', $u->correo)->update(['fecha_creacion' => now()->subMinutes(61)]);
            $this->assertFalse(Password::tokenExists($u, $aviso->token));
            DB::table('codigos_restablecimiento')->where('correo', $u->correo)->update(['fecha_creacion' => now()]);

            return true;
        });
        $this->postJson('/api/v1/acceso/solicitar-restablecimiento', ['correo' => $u->correo])->assertOk();
        Notification::assertSentToTimes($u, ResetPassword::class, 1);
        DB::table('codigos_restablecimiento')->update(['fecha_creacion' => now()->subMinutes(61)]);
        $this->artisan('auth:clear-resets')->assertSuccessful();
        $this->assertDatabaseCount('codigos_restablecimiento', 0);
    }
}
