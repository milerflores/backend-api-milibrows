<?php

namespace Tests\Feature;

use App\Models\Aviso;
use App\Models\Cita;
use App\Models\ConfiguracionEstudio;
use App\Modules\Avisos\AvisosService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Soporte;
use Tests\TestCase;

class AvisosTest extends TestCase
{
    use RefreshDatabase, Soporte;

    public function test_avisos_deduplicados_y_recordatorios_obsoletos_no_se_envian(): void
    {
        [$u, $e, $ss, $d] = $this->escenario();
        ConfiguracionEstudio::find(1)->update(['canal_notificacion' => 'correo']);
        $id = $this->actingAs($u)->postJson('/api/v1/citas', $d)->assertCreated()->json('datos.id');
        $a = Aviso::where('tipo', 'confirmacion')->first();
        $avisos = app(AvisosService::class);
        $avisos->enviar($a->id);
        $avisos->enviar($a->id);
        $this->assertSame('enviada', $a->fresh()->estado);

        $recordatorio = Aviso::where('tipo', 'recordatorio')->first();
        $nuevaFecha = Cita::find($id)->fecha->addDay()->format('Y-m-d');
        $this->postJson('/api/v1/citas/'.$id.'/reprogramar', ['fecha' => $nuevaFecha])->assertOk();

        $recordatorio->update(['estado' => 'pendiente', 'fecha_programada' => now()]);
        $avisos->enviar($recordatorio->id);
        $this->assertSame('invalidada', $recordatorio->fresh()->estado);

        $this->postJson('/api/v1/citas/'.$id.'/cancelar')->assertOk();
        $vigente = Aviso::where('revision_cita', 2)->where('tipo', 'recordatorio')->first();
        $avisos->enviar($vigente->id);
        $this->assertSame('invalidada', $vigente->fresh()->estado);
    }
}
