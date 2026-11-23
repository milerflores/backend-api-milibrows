<?php

namespace Tests;

use App\Models\Cliente;
use App\Models\Empleado;
use App\Models\JornadaEmpleado;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use Carbon\CarbonImmutable;

trait Soporte
{
    protected function usuario(string $rol = 'cliente'): User
    {
        $p = Persona::create([
            'nombre' => fake()->firstName(),
            'apellido_paterno' => fake()->lastName(),
            'apellido_materno' => fake()->lastName(),
            'correo_contacto' => fake()->unique()->safeEmail(),
        ]);
        $u = User::factory()->create(['id_persona' => $p->id]);
        $u->roles()->attach(Rol::where('codigo', $rol)->value('id'));
        if ($rol === 'cliente') {
            Cliente::create(['id_persona' => $p->id, 'activo' => true]);
        } else {
            Empleado::create(['id_usuario' => $u->id, 'cargo' => $rol, 'admite_reservas' => $rol === 'estilista']);
        }

        return $u->fresh(['persona.cliente', 'empleado', 'roles']);
    }

    protected function escenario(): array
    {
        $cliente = $this->usuario();
        $estilista = $this->usuario('estilista');
        $e = $estilista->empleado;
        foreach (range(1, 6) as $dia) {
            JornadaEmpleado::create(['id_empleado' => $e->id, 'dia_semana' => $dia, 'hora_inicio' => '10:00', 'hora_fin' => '22:00']);
        }
        $servicios = collect();
        foreach (range(1, 3) as $i) {
            $servicios->push(Servicio::create(['nombre' => 'Servicio '.$i, 'precio' => '30.00', 'duracion_minutos' => 30]));
        }
        $e->servicios()->attach($servicios->pluck('id')->all(), ['activo' => true]);
        $inicio = CarbonImmutable::now()->next('Monday')->setTime(12, 0);
        $lineas = [];
        $cursor = $inicio;
        foreach ($servicios as $s) {
            $lineas[] = [
                'id_servicio' => $s->id,
                'id_empleado' => $e->id,
                'hora_inicio' => $cursor->format('H:i'),
            ];
            $cursor = $cursor->addMinutes($s->duracion_minutos);
        }

        return [
            $cliente,
            $estilista,
            $servicios,
            [
                'fecha' => $inicio->format('Y-m-d'),
                'servicios' => $lineas,
            ],
        ];
    }
}
