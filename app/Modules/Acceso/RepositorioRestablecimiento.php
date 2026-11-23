<?php

namespace App\Modules\Acceso;

use Illuminate\Auth\Passwords\DatabaseTokenRepository;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Carbon;

class RepositorioRestablecimiento extends DatabaseTokenRepository
{
    protected function deleteExisting(CanResetPassword $user)
    {
        return $this->getTable()->where('correo', $user->getEmailForPasswordReset())->delete();
    }

    protected function getPayload($email, #[\SensitiveParameter] $token)
    {
        return ['correo' => $email, 'codigo' => $this->hasher->make($token), 'fecha_creacion' => new Carbon];
    }

    public function exists(CanResetPassword $user, #[\SensitiveParameter] $token)
    {
        $registro = $this->getTable()->where('correo', $user->getEmailForPasswordReset())->first();

        return $registro && ! $this->tokenExpired($registro->fecha_creacion)
            && $this->hasher->check($token, $registro->codigo);
    }

    public function recentlyCreatedToken(CanResetPassword $user)
    {
        $registro = $this->getTable()->where('correo', $user->getEmailForPasswordReset())->first();

        return $registro && $this->tokenRecentlyCreated($registro->fecha_creacion);
    }

    public function deleteExpired()
    {
        $this->getTable()->where('fecha_creacion', '<', Carbon::now()->subSeconds($this->expires))->delete();
    }
}
