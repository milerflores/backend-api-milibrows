<?php

namespace App\Providers;

use App\Modules\Acceso\GestorRestablecimiento;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Fortify;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Fortify::ignoreRoutes();
        $this->app->extend('auth.password', fn ($manager, $app) => new GestorRestablecimiento($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('milibrows.frontend_url'), '/').'/restablecer-contrasena?'.http_build_query(['token' => $token, 'correo' => $user->correo]));
        RateLimiter::for('acceso', fn (Request $r) => Limit::perMinute(5)->by($r->ip().'|'.mb_strtolower((string) $r->input('correo'))));
    }
}
