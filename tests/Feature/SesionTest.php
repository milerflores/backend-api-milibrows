<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte;
use Tests\TestCase;

class SesionTest extends TestCase
{
    use RefreshDatabase, Soporte;

    public function test_cookies_reales_de_sanctum_sobreviven_a_la_regeneracion_de_sesion(): void
    {
        $u = $this->usuario();
        $this->app['env'] = 'local';
        $this->withCredentials();
        $inicio = $this->getJson('/api/v1/acceso/cookie-csrf')->assertNoContent();
        $copiar = function ($respuesta) {
            foreach ([config('session.cookie'), 'XSRF-TOKEN'] as $nombre) {
                $this->withUnencryptedCookie($nombre, $respuesta->getCookie($nombre, false)->getValue());
            }
            $this->withHeader('X-XSRF-TOKEN', $respuesta->getCookie('XSRF-TOKEN', false)->getValue());
        };
        $copiar($inicio);
        $login = $this->postJson('/api/v1/acceso/iniciar-sesion', ['correo' => $u->correo, 'contrasena' => 'password'])->assertOk();
        $copiar($login);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/mi-perfil')->assertOk()->assertJsonPath('datos.id', $u->id);
        $this->postJson('/api/v1/acceso/cerrar-sesion')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/mi-perfil')->assertUnauthorized();
    }

    public function test_sesion_web_csrf_login_logout_y_cuenta_inactiva(): void
    {
        $u = $this->usuario();
        $this->app['env'] = 'local'; // Exercise CSRF, which Laravel normally bypasses in tests.
        $this->getJson('/api/v1/acceso/cookie-csrf')->assertNoContent()->assertCookie('XSRF-TOKEN');
        $datos = ['correo' => $u->correo, 'contrasena' => 'password'];
        $this->postJson('/api/v1/acceso/iniciar-sesion', $datos)->assertStatus(419);
        $this->withSession(['_token' => 'token-conocido'])->withHeader('X-CSRF-TOKEN', 'token-conocido');
        $this->postJson('/api/v1/acceso/iniciar-sesion', $datos)->assertOk()->assertJsonPath('datos.id', $u->id);
        $this->getJson('/api/v1/mi-perfil')->assertOk();
        $this->withHeader('X-CSRF-TOKEN', $this->app['session']->token());
        $this->withHeader('X-CSRF-TOKEN', $this->app['session']->token());
        $this->postJson('/api/v1/acceso/cerrar-sesion')->assertNoContent();
        $u->update(['activo' => false]);
        $this->withSession(['_token' => 'otro-token'])->withHeader('X-CSRF-TOKEN', 'otro-token');
        $this->postJson('/api/v1/acceso/iniciar-sesion', $datos)->assertUnprocessable();
    }
}
