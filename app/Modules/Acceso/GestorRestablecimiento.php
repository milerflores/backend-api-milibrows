<?php

namespace App\Modules\Acceso;

use Illuminate\Auth\Passwords\PasswordBrokerManager;

class GestorRestablecimiento extends PasswordBrokerManager
{
    protected function createTokenRepository(array $config)
    {
        if (($config['driver'] ?? 'database') === 'cache') {
            return parent::createTokenRepository($config);
        }

        $key = $this->app['config']['app.key'];
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(substr($key, 7));
        }

        return new RepositorioRestablecimiento(
            $this->app['db']->connection($config['connection'] ?? null),
            $this->app['hash'],
            $config['table'],
            $key,
            ($config['expire'] ?? 60) * 60,
            $config['throttle'] ?? 0,
        );
    }
}
