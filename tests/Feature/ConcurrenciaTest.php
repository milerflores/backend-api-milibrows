<?php

namespace Tests\Feature;

use App\Models\Cita;
use App\Models\Insumo;
use App\Models\MetodoPago;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Soporte;
use Tests\TestCase;

class ConcurrenciaTest extends TestCase
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

    private function simultaneas(string $modo, int $u, array $d): array
    {
        $inicio = microtime(true) + 2;
        $cmd = [PHP_BINARY, base_path('tests/Support/concurrente.php'), config('database.connections.mysql.database'), $modo, (string) $u, base64_encode(json_encode($d)), (string) $inicio];
        $procesos = [];
        for ($i = 0; $i < 2; $i++) {
            $proceso = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path());
            $this->assertIsResource($proceso);
            fclose($pipes[0]);
            $procesos[] = [$proceso, $pipes];
        }
        $estados = [];
        foreach ($procesos as [$proceso, $pipes]) {
            $salida = stream_get_contents($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $this->assertSame(0, proc_close($proceso), $error.' '.$salida);
            $estados[] = json_decode($salida, true, 512, JSON_THROW_ON_ERROR)['estado'];
        }
        sort($estados);

        return $estados;
    }

    public function test_mysql_serializa_reservas_cobros_y_entregas_reales(): void
    {
        [$cliente, $e, $ss, $d] = $this->escenario();
        $d['id_cliente'] = $cliente->idCliente();
        $this->assertSame([201, 409], $this->simultaneas('reserva', $cliente->id, $d));
        $this->assertDatabaseCount('citas', 1);

        $c = Cita::with('servicios')->first();
        $c->servicios()->update(['estado_servicio' => 'realizado']);
        $c->update(['estado' => 'completada']);
        $total = number_format((float) $c->servicios()->sum('precio_acordado'), 2, '.', '');

        $recepcion = $this->usuario('recepcion');
        // Dos cobros simultáneos por el total completo: solo uno debe entrar, el segundo excede saldo (422).
        $pago = ['id_cita' => $c->id, 'id_metodo_pago' => MetodoPago::first()->id, 'importe' => $total];
        $this->assertSame([201, 422], $this->simultaneas('cobro', $recepcion->id, $pago));
        $this->assertDatabaseCount('cobros', 1);

        $admin = $this->usuario('administracion');
        // Stock 5, dos entregas simultáneas de 3: solo una debe pasar (201) y la otra fallar por stock (409).
        $i = Insumo::create(['id_categoria' => 1, 'nombre' => 'Prueba', 'existencias_actuales' => 5]);
        $entrega = ['id_insumo' => $i->id, 'id_empleado' => $e->empleado->id, 'cantidad_entregada' => 3];
        $this->assertSame([201, 409], $this->simultaneas('stock', $admin->id, $entrega));
        $this->assertSame(2, $i->fresh()->existencias_actuales);
        $this->assertDatabaseCount('asignaciones_insumos', 1);
        $this->assertDatabaseCount('movimientos_inventario', 1);
    }
}
