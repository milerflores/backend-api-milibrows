<?php

namespace Tests\Feature;

use App\Models\Servicio;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Soporte;
use Tests\TestCase;

class ServiciosImagenesTest extends TestCase
{
    use DatabaseTruncation, Soporte;

    protected array $exceptTables = [
        'migrations',
        'roles',
        'configuracion_estudio',
        'horarios_estudio',
        'metodos_pago',
        'categorias_inventario',
        'niveles_fidelizacion',
    ];

    private function administrador(): void
    {
        $u = $this->usuario('administracion');
        $u->forceFill(['fecha_confirmacion_mfa' => now()])->save();
        $this->actingAs($u)->withSession(['mfa_usuario' => $u->id]);
        Storage::fake('public');
    }

    private function imagen(string $nombre = 'servicio.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='
        ));
    }

    public function test_un_fallo_al_guardar_limpia_la_imagen_nueva_y_conserva_la_anterior(): void
    {
        $this->administrador();
        $s = Servicio::create(['nombre' => 'Diseño', 'precio' => 55, 'duracion_minutos' => 45]);
        $this->post('/api/v1/administracion/servicios/'.$s->id, ['_method' => 'PATCH', 'imagen' => $this->imagen()], ['Accept' => 'application/json'])->assertOk();
        $anterior = $s->fresh()->imagen_ruta;
        Servicio::saving(function () {
            throw new RuntimeException('Fallo de persistencia simulado.');
        });
        try {
            $this->post('/api/v1/administracion/servicios/'.$s->id, ['_method' => 'PATCH', 'imagen' => $this->imagen('reemplazo.png')], ['Accept' => 'application/json'])
                ->assertStatus(500);
            $this->assertSame($anterior, $s->fresh()->imagen_ruta);
            Storage::disk('public')->assertExists($anterior);
            $this->assertCount(1, Storage::disk('public')->allFiles('servicios'));
        } finally {
            Servicio::flushEventListeners();
        }
    }

    public function test_crear_editar_reemplazar_y_eliminar_imagen_conserva_el_servicio(): void
    {
        $this->administrador();
        $id = $this->post('/api/v1/administracion/servicios', [
            'nombre' => 'Diseño',
            'precio' => '55.00',
            'duracion_minutos' => 45,
            'descripcion' => 'Diseño personalizado.',
            'imagen' => $this->imagen(),
        ], ['Accept' => 'application/json'])->assertCreated()
            ->assertJsonPath('datos.descripcion', 'Diseño personalizado.')
            ->assertJsonMissingPath('datos.imagen_ruta')->json('datos.id');
        $s = Servicio::findOrFail($id);
        $primera = $s->imagen_ruta;
        Storage::disk('public')->assertExists($primera);
        $this->getJson('/api/v1/servicios')->assertOk()
            ->assertJsonPath('datos.0.descripcion', 'Diseño personalizado.')
            ->assertJsonPath('datos.0.imagen_url', $s->imagen_url);
        $this->get($s->imagen_url)->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->patchJson('/api/v1/administracion/servicios/'.$id, ['nombre' => 'Diseño actualizado'])
            ->assertOk()->assertJsonPath('datos.imagen_url', $s->imagen_url);
        $this->post('/api/v1/administracion/servicios/'.$id, [
            '_method' => 'PATCH', 'descripcion' => 'Descripción nueva.', 'imagen' => $this->imagen('nueva.png'),
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('datos.descripcion', 'Descripción nueva.');
        $segunda = $s->fresh()->imagen_ruta;
        $this->assertNotSame($primera, $segunda);
        Storage::disk('public')->assertMissing($primera);
        Storage::disk('public')->assertExists($segunda);

        $this->patchJson('/api/v1/administracion/servicios/'.$id, ['eliminar_imagen' => true, 'descripcion' => null])
            ->assertOk()->assertJsonPath('datos.imagen_url', null)->assertJsonPath('datos.descripcion', null);
        Storage::disk('public')->assertMissing($segunda);
        $this->get('/api/v1/servicios/'.$id.'/imagen')->assertNotFound();
        $this->assertSame('55.00', $s->fresh()->precio);
    }

    public function test_imagenes_invalidas_y_usuarios_sin_permiso_no_modifican_archivos(): void
    {
        $this->administrador();
        $s = Servicio::create(['nombre' => 'Diseño', 'precio' => 55, 'duracion_minutos' => 45]);
        $url = '/api/v1/administracion/servicios/'.$s->id;
        foreach ([
            UploadedFile::fake()->createWithContent('archivo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
            $this->imagen()->size(2049),
        ] as $imagen) {
            $this->post($url, ['_method' => 'PATCH', 'imagen' => $imagen], ['Accept' => 'application/json'])
                ->assertUnprocessable()->assertJsonValidationErrors('imagen', 'errores');
        }
        $this->post($url, ['_method' => 'PATCH', 'imagen' => $this->imagen(), 'eliminar_imagen' => '1'], ['Accept' => 'application/json'])
            ->assertUnprocessable();
        $this->assertCount(0, Storage::disk('public')->allFiles('servicios'));
        $this->actingAs($this->usuario('recepcion'))->post($url, ['_method' => 'PATCH', 'imagen' => $this->imagen()], ['Accept' => 'application/json'])
            ->assertForbidden();
        $this->assertNull($s->fresh()->imagen_ruta);
        $this->assertCount(0, Storage::disk('public')->allFiles('servicios'));
    }
}
