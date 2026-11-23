<?php

namespace App\Console\Commands;

use App\Models\Empleado;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CrearAdministrador extends Command
{
    protected $signature = 'milibrows:crear-admin {correo} {nombre} {apellido_paterno} {apellido_materno?}';

    protected $description = 'Crear una cuenta administrativa inicial sin contraseñas en el código';

    public function handle(): int
    {
        $clave = $this->secret('Contraseña inicial (10 caracteres, mayúsculas, minúsculas y números)');
        $v = Validator::make([
            'correo' => $this->argument('correo'),
            'nombre' => $this->argument('nombre'),
            'apellido_paterno' => $this->argument('apellido_paterno'),
            'apellido_materno' => $this->argument('apellido_materno'),
            'clave' => $clave,
        ], [
            'correo' => 'required|email|max:255|unique:usuarios,correo',
            'nombre' => 'required|string|max:100',
            'apellido_paterno' => 'required|string|max:100',
            'apellido_materno' => 'nullable|string|max:100',
            'clave' => ['required', Password::min(10)->mixedCase()->numbers()],
        ]);
        if ($v->fails()) {
            $this->error('Datos inválidos; revise correo, nombres y requisitos de contraseña.');

            return self::FAILURE;
        }
        DB::transaction(function () use ($clave) {
            $p = Persona::create([
                'nombre' => $this->argument('nombre'),
                'apellido_paterno' => $this->argument('apellido_paterno'),
                'apellido_materno' => $this->argument('apellido_materno'),
                'correo_contacto' => mb_strtolower($this->argument('correo')),
            ]);
            $u = User::create([
                'id_persona' => $p->id,
                'correo' => mb_strtolower($this->argument('correo')),
                'contrasena' => $clave,
            ]);
            $u->roles()->attach(Rol::where('codigo', 'administracion')->value('id'));
            Empleado::create(['id_usuario' => $u->id, 'cargo' => 'Administración']);
        });
        $this->info('Cuenta creada. Inicie sesión y configure MFA para acceder al panel.');

        return self::SUCCESS;
    }
}
