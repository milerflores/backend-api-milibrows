<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLAS = [
        'users' => 'usuarios',
        'password_reset_tokens' => 'codigos_restablecimiento',
    ];

    private const CAMPOS_USUARIO = [
        'name' => 'nombre',
        'email' => 'correo',
        'email_verified_at' => 'fecha_verificacion_correo',
        'password' => 'contrasena',
        'remember_token' => 'token_recordatorio',
        'two_factor_secret' => 'secreto_segundo_factor',
        'two_factor_recovery_codes' => 'codigos_respaldo_mfa',
        'two_factor_confirmed_at' => 'fecha_confirmacion_mfa',
    ];

    private const FECHAS = ['created_at' => 'fecha_creacion', 'updated_at' => 'fecha_actualizacion'];

    private const TABLAS_CON_FECHAS = ['personas', 'clientes', 'empleados', 'servicios', 'citas', 'insumos'];

    public function up(): void
    {
        foreach (self::TABLAS as $anterior => $nuevo) {
            Schema::rename($anterior, $nuevo);
        }

        $this->renombrar('usuarios', self::CAMPOS_USUARIO + self::FECHAS);
        $this->renombrar('codigos_restablecimiento', ['email' => 'correo', 'token' => 'codigo', 'created_at' => 'fecha_creacion']);
        foreach (self::TABLAS_CON_FECHAS as $tabla) {
            $this->renombrar($tabla, self::FECHAS);
        }
    }

    public function down(): void
    {
        foreach (self::TABLAS_CON_FECHAS as $tabla) {
            $this->renombrar($tabla, array_flip(self::FECHAS));
        }
        $this->renombrar('codigos_restablecimiento', ['correo' => 'email', 'codigo' => 'token', 'fecha_creacion' => 'created_at']);
        $this->renombrar('usuarios', array_flip(self::CAMPOS_USUARIO + self::FECHAS));

        foreach (self::TABLAS as $anterior => $nuevo) {
            Schema::rename($nuevo, $anterior);
        }
    }

    private function renombrar(string $tabla, array $campos): void
    {
        Schema::table($tabla, function (Blueprint $t) use ($campos) {
            foreach ($campos as $anterior => $nuevo) {
                $t->renameColumn($anterior, $nuevo);
            }
        });
    }
};
