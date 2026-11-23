<?php

namespace Database\Factories;

use App\Models\Persona;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'id_persona' => fn () => Persona::create([
                'nombre' => fake()->firstName(),
                'apellido_paterno' => fake()->lastName(),
                'apellido_materno' => fake()->lastName(),
                'correo_contacto' => fake()->unique()->safeEmail(),
            ])->id,
            'correo' => fake()->unique()->safeEmail(),
            'fecha_verificacion_correo' => now(),
            'contrasena' => static::$password ??= Hash::make('password'),
            'version_sesion' => 1,
            'activo' => true,
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'fecha_verificacion_correo' => null,
        ]);
    }
}
