<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

    public const CREATED_AT = 'fecha_creacion';

    public const UPDATED_AT = 'fecha_actualizacion';

    protected $table = 'usuarios';

    protected $fillable = ['correo', 'contrasena', 'id_persona', 'activo'];

    protected $attributes = ['activo' => true, 'version_sesion' => 1];

    protected $hidden = ['contrasena', 'secreto_segundo_factor', 'codigos_respaldo_mfa'];

    public function getAuthPasswordName(): string
    {
        return 'contrasena';
    }

    public function getRememberTokenName(): string
    {
        return '';
    }

    public function setRememberToken($value): void {}

    public function getEmailForPasswordReset(): string
    {
        return $this->correo;
    }

    public function routeNotificationForMail(): string
    {
        return $this->getEmailForVerification();
    }

    public function getEmailForVerification(): string
    {
        return $this->correo;
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->fecha_verificacion_correo !== null;
    }

    public function markEmailAsVerified(): bool
    {
        return $this->forceFill(['fecha_verificacion_correo' => $this->freshTimestamp()])->save();
    }

    // Fortify utiliza estos atributos nativos; solo se persisten sus equivalentes en español.
    protected function twoFactorSecret(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->secreto_segundo_factor,
            set: fn ($valor) => ['secreto_segundo_factor' => $valor],
        );
    }

    protected function twoFactorRecoveryCodes(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->codigos_respaldo_mfa,
            set: fn ($valor) => ['codigos_respaldo_mfa' => $valor],
        );
    }

    protected function twoFactorConfirmedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->fecha_confirmacion_mfa,
            set: fn ($valor) => ['fecha_confirmacion_mfa' => $valor],
        );
    }

    protected function casts(): array
    {
        return [
            'fecha_verificacion_correo' => 'datetime',
            'contrasena' => 'hashed',
            'version_sesion' => 'integer',
            'activo' => 'boolean',
            'fecha_confirmacion_mfa' => 'datetime',
        ];
    }

    public function persona()
    {
        return $this->belongsTo(Persona::class, 'id_persona');
    }

    public function empleado()
    {
        return $this->hasOne(Empleado::class, 'id_usuario');
    }

    public function roles()
    {
        return $this->belongsToMany(Rol::class, 'roles_usuario', 'id_usuario', 'id_rol');
    }

    public function es(string ...$roles): bool
    {
        return $this->roles->pluck('codigo')->intersect($roles)->isNotEmpty();
    }

    public function idCliente(): ?int
    {
        return $this->persona?->cliente?->id;
    }

    public function perfil(): array
    {
        return [
            'id' => $this->id,
            'correo' => $this->correo,
            'activo' => $this->activo,
            'roles' => $this->roles->pluck('codigo'),
            'persona' => $this->persona,
            'id_cliente' => $this->idCliente(),
            'id_empleado' => $this->empleado?->id,
            'segundo_factor_configurado' => $this->fecha_confirmacion_mfa !== null,
        ];
    }
}
