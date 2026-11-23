<?php

namespace Database\Seeders;

use App\Models\Aviso;
use App\Models\CategoriaInventario;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\ConfiguracionEstudio;
use App\Models\Empleado;
use App\Models\HistorialCita;
use App\Models\Insumo;
use App\Models\MetodoPago;
use App\Models\MotivoDescuento;
use App\Models\NivelFidelizacion;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Servicio;
use App\Models\User;
use App\Modules\Agenda\AgendaService;
use App\Modules\Agenda\Disponibilidad;
use App\Modules\Caja\CajaService;
use App\Modules\Inventario\InventarioService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

class DatosPruebaSeeder extends Seeder
{
    private array $accesos;

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('Los datos DEMO solo se cargan en entornos local o testing.');
        }

        $archivo = storage_path('app/private/datos-prueba.json');
        $this->accesos = File::exists($archivo) ? json_decode(File::get($archivo), true, 512, JSON_THROW_ON_ERROR) : [];
        foreach (['admin' => 'administracion', 'recepcion' => 'recepcion', 'estilista1' => 'estilista', 'estilista2' => 'estilista', 'cliente1' => 'cliente', 'cliente2' => 'cliente'] as $cuenta => $rol) {
            $correo = $cuenta.'.demo@milibrows.test';
            if (! isset($this->accesos[$cuenta])) {
                if (User::where('correo', $correo)->exists()) {
                    throw new RuntimeException('Ya existe '.$correo.' sin credenciales DEMO locales; no se modificó su contraseña.');
                }
                $this->accesos[$cuenta] = ['correo' => $correo, 'contrasena' => Str::password(18), 'rol' => $rol];
            }
        }
        File::ensureDirectoryExists(dirname($archivo));
        File::put($archivo, json_encode($this->accesos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), true);

        DB::transaction(function () {
            ConfiguracionEstudio::lockForUpdate()->findOrFail(1);
            $admin = $this->cuenta('admin', 'Admin', 'Demo');
            $recepcion = $this->cuenta('recepcion', 'Recepción', 'Demo');
            $e1 = $this->cuenta('estilista1', 'Mariana', 'Torres')->empleado;
            $e2 = $this->cuenta('estilista2', 'Camila', 'Rojas')->empleado;
            $ana = $this->cuenta('cliente1', 'Ana', 'García', null, '90000001')->persona->cliente;
            $lucia = $this->cuenta('cliente2', 'Lucía', 'Mendoza', null, '90000002')->persona->cliente;
            $valeria = $this->ficha('Valeria', 'Flores', '90000003');
            $this->ficha('Daniela', 'Vargas', '90000004');

            foreach (['Promoción del mes', 'Cortesía del estudio', 'Cumpleaños'] as $motivo) {
                MotivoDescuento::firstOrCreate(['nombre' => $motivo], ['activo' => true]);
            }

            $servicios = [];
            foreach ([
                ['perfilado', 'Perfilado de cejas', 35, 30],
                ['laminado', 'Laminado de cejas', 80, 60],
                ['tinte', 'Tinte de cejas', 35, 30],
                ['lifting', 'Lifting de pestañas', 100, 60],
                ['extensiones', 'Extensiones clásicas', 150, 120],
                ['limpieza', 'Limpieza facial', 90, 60],
                ['hidratacion', 'Hidratación facial', 60, 45],
                ['depilacion', 'Depilación de bozo', 25, 20],
            ] as [$clave, $nombre, $precio, $duracion]) {
                $servicios[$clave] = Servicio::firstOrCreate(
                    ['nombre' => '[DEMO] '.$nombre],
                    ['precio' => $precio, 'duracion_minutos' => $duracion, 'activo' => true, 'visible_al_publico' => true]
                );
            }

            $this->habilitar($e1, collect($servicios)->except(['limpieza', 'hidratacion'])->all());
            $this->habilitar($e2, $servicios);

            $base = CarbonImmutable::today()->startOfWeek()->subWeek();
            $parcial = $this->historica('parcial', $ana, $e1, [$servicios['perfilado'], $servicios['laminado'], $servicios['tinte']],
                $base->setTime(11, 0), $recepcion, 'completada', ['realizado', 'realizado', 'no_realizado']);
            $perfilado = $this->historica('perfilado', $ana, $e1, [$servicios['perfilado']],
                $base->addDay()->setTime(12, 0), $recepcion, 'completada', ['realizado']);
            $pestanas = $this->historica('pestanas', $lucia, $e2, [$servicios['lifting'], $servicios['extensiones']],
                $base->addDays(2)->setTime(11, 0), $recepcion, 'completada', ['realizado', 'realizado']);
            $this->historica('sin-cobrar', $valeria, $e1, [$servicios['perfilado'], $servicios['tinte']],
                $base->addDays(3)->setTime(15, 0), $recepcion, 'completada', ['realizado', 'realizado']);
            $this->historica('cancelada', $lucia, $e1, [$servicios['laminado']],
                $base->addDays(4)->setTime(14, 0), $recepcion, 'cancelada');
            $this->historica('inasistencia', $ana, $e2, [$servicios['limpieza']],
                $base->addDays(5)->setTime(16, 0), $recepcion, 'no_asistio');

            $this->futura('futura-ana', $ana, $e1, [$servicios['perfilado'], $servicios['tinte']], $recepcion);
            $this->futura('futura-lucia', $lucia, $e2, [$servicios['limpieza'], $servicios['hidratacion']], $recepcion);
            $this->futura('futura-valeria', $valeria, $e1, [$servicios['laminado']], $recepcion);

            $this->cobros($recepcion, [$parcial, $perfilado, $pestanas]);
            $this->inventario($admin, $e1, $e2);
        }, 3);
        $this->command?->info('Datos DEMO cargados en '.DB::connection()->getDatabaseName().'. Credenciales: '.$archivo);
    }

    private function cuenta(string $clave, string $nombre, string $paterno, ?string $materno = null, ?string $dni = null): User
    {
        $datos = $this->accesos[$clave];
        if ($u = User::where('correo', $datos['correo'])->first()) {
            return $u->load(['persona.cliente', 'empleado', 'roles']);
        }
        if ($dni && Persona::where('dni', $dni)->exists()) {
            throw new RuntimeException('El DNI DEMO '.$dni.' ya existe; no se vincularon datos ajenos.');
        }
        $persona = Persona::create([
            'nombre' => $nombre,
            'apellido_paterno' => $paterno,
            'apellido_materno' => $materno,
            'dni' => $dni,
            'telefono' => '900000000',
            'correo_contacto' => $datos['correo'],
        ]);
        $u = User::create([
            'id_persona' => $persona->id,
            'correo' => $datos['correo'],
            'contrasena' => $datos['contrasena'],
        ]);
        $u->roles()->attach(Rol::where('codigo', $datos['rol'])->sole()->id);
        if ($datos['rol'] === 'cliente') {
            Cliente::create(['id_persona' => $persona->id, 'preferencias' => '[DEMO] Datos ficticios.', 'activo' => true]);
        } else {
            Empleado::create(['id_usuario' => $u->id, 'cargo' => $datos['rol'], 'admite_reservas' => $datos['rol'] === 'estilista']);
        }

        return $u->fresh(['persona.cliente', 'empleado', 'roles']);
    }

    private function ficha(string $nombre, string $paterno, string $dni): Cliente
    {
        $p = Persona::where('dni', $dni)->first();
        $p ??= Persona::create(['nombre' => $nombre, 'apellido_paterno' => $paterno, 'dni' => $dni, 'telefono' => '900000000']);

        return Cliente::firstOrCreate(['id_persona' => $p->id], ['preferencias' => '[DEMO] Ficha creada sin cuenta.', 'activo' => true]);
    }

    private function habilitar(Empleado $e, array $servicios): void
    {
        foreach (range(1, 6) as $dia) {
            $e->jornada()->firstOrCreate(['dia_semana' => $dia], ['hora_inicio' => '10:00', 'hora_fin' => '19:00']);
        }
        $e->servicios()->syncWithoutDetaching(collect($servicios)->mapWithKeys(fn ($s) => [$s->id => ['activo' => true]])->all());
    }

    private function historica(string $clave, Cliente $cliente, Empleado $e, array $servicios, CarbonImmutable $inicio, User $autor, string $estado, array $resultados = []): Cita
    {
        $origen = 'demo:'.$clave;
        if ($c = Cita::where('origen', $origen)->first()) {
            return $c;
        }
        $nivel = NivelFidelizacion::where('codigo', 'glow')->first();
        $c = Cita::create([
            'id_cliente' => $cliente->id,
            'id_usuario_creador' => $autor->id,
            'fecha' => $inicio->format('Y-m-d'),
            'origen' => $origen,
            'estado' => 'confirmada',
            'numero_revision' => 1,
            'fecha_creacion' => $inicio->subDay(),
        ]);
        $cursor = $inicio;
        foreach ($servicios as $s) {
            $lista = (float) $s->precio;
            $desc = round($lista * ((float) ($nivel?->porcentaje_descuento ?? 0) / 100), 2);
            $c->servicios()->create([
                'id_servicio' => $s->id,
                'id_empleado' => $e->id,
                'id_nivel' => $nivel?->id,
                'nombre_servicio_acordado' => $s->nombre,
                'precio_lista' => number_format($lista, 2, '.', ''),
                'monto_descuento_automatico' => number_format($desc, 2, '.', ''),
                'precio_acordado' => number_format($lista - $desc, 2, '.', ''),
                'duracion_minutos' => $s->duracion_minutos,
                'hora_inicio' => $cursor->format('H:i:s'),
                'estado_servicio' => 'pendiente',
            ]);
            $cursor = $cursor->addMinutes($s->duracion_minutos);
        }
        HistorialCita::create([
            'id_cita' => $c->id,
            'id_usuario_responsable' => $autor->id,
            'numero_revision' => 1,
            'tipo_cambio' => 'creada',
            'detalle_cambios' => ['origen' => 'fixture_historica_demo', 'estado' => 'confirmada'],
            'fecha_creacion' => $inicio->subDay(),
        ]);

        $accion = match ($estado) {
            'completada' => 'completar',
            'cancelada' => 'cancelar',
            default => 'marcar-inasistencia',
        };
        $datos = ['motivo' => '[DEMO] Cancelación de ejemplo por recepción.'];
        if ($estado === 'completada') {
            $datos = [
                'servicios' => $c->servicios->map(fn ($s, $i) => [
                    'id_servicio_cita' => $s->id,
                    'estado_servicio' => $resultados[$i],
                    'motivo_estado_servicio' => $resultados[$i] === 'realizado' ? null : '[DEMO] La clienta decidió omitir este servicio.',
                ])->all(),
                'nota' => ['descripcion_tratamiento' => '[DEMO] Atención de prueba.', 'observaciones' => 'Datos ficticios.'],
            ];
        }
        $c = app(AgendaService::class)->cambiar($estado === 'completada' ? $e->usuario : $autor, $c->id, $accion, $datos);
        if ($estado === 'completada') {
            $c->update(['fecha_finalizacion' => $cursor]);
        }

        return $c->fresh();
    }

    private function futura(string $clave, Cliente $cliente, Empleado $e, array $servicios, User $autor): void
    {
        if (Cita::where('origen', 'demo:'.$clave)->exists()) {
            return;
        }
        $disponibilidad = app(Disponibilidad::class);
        $duracion = collect($servicios)->sum('duracion_minutos');
        for ($dia = 1; $dia <= 21; $dia++) {
            $fecha = CarbonImmutable::today()->addDays($dia);
            foreach ($disponibilidad->intervalos($e, $fecha, $duracion) as [$inicio, $fin]) {
                $inicio = $inicio->ceilMinutes(15);
                if ($inicio->addMinutes($duracion) > $fin) {
                    continue;
                }
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
                $c = app(AgendaService::class)->reservar($autor, [
                    'id_cliente' => $cliente->id,
                    'fecha' => $fecha->format('Y-m-d'),
                    'servicios' => $lineas,
                ]);
                $c->update(['origen' => 'demo:'.$clave]);
                Aviso::where('id_cita', $c->id)->update(['estado' => 'invalidada']);

                return;
            }
        }
        throw new RuntimeException('No hay horario disponible para una cita DEMO en los próximos 21 días.');
    }

    private function cobros(User $recepcion, array $citas): void
    {
        $caja = app(CajaService::class);
        $metodos = ['efectivo', 'yape', 'tarjeta'];
        foreach ($citas as $i => $c) {
            if ($c->cobros()->exists()) {
                continue;
            }
            $total = (float) $c->servicios()->where('estado_servicio', 'realizado')->sum('precio_acordado');
            $metodoId = MetodoPago::where('codigo', $metodos[$i % count($metodos)])->value('id')
                ?? MetodoPago::where('codigo', 'efectivo')->sole()->id;
            $caja->cobrar($recepcion, [
                'id_cita' => $c->id,
                'id_metodo_pago' => $metodoId,
                'importe' => number_format($total, 2, '.', ''),
                'referencia_pago' => '[DEMO] Cobro ficticio',
            ]);
        }
    }

    private function inventario(User $admin, Empleado $e1, Empleado $e2): void
    {
        $categoria = CategoriaInventario::firstOrCreate(['nombre' => '[DEMO] Material del estudio']);
        $s = app(InventarioService::class);
        foreach ([['Guantes', 100, 20, 10], ['Cepillos de cejas', 30, 10, 5], ['Pigmento de cejas', 8, 3, 2], ['Adhesivo de pestañas', 4, 3, 1], ['Discos de algodón', 80, 20, 10]] as $i => [$nombre, $stock, $minimo, $cantidad]) {
            $insumo = Insumo::firstOrCreate(
                ['nombre' => '[DEMO] '.$nombre, 'id_categoria' => $categoria->id],
                ['existencias_minimas' => $minimo, 'existencias_actuales' => 0, 'activo' => true]
            );
            if ($insumo->existencias_actuales === 0) {
                $s->mover($admin, [
                    'id_insumo' => $insumo->id,
                    'tipo_movimiento' => 'entrada',
                    'variacion_cantidad' => $stock,
                    'motivo' => '[DEMO] Existencias iniciales',
                ]);
                $asignacion = $s->entregar($admin, [
                    'id_insumo' => $insumo->id,
                    'id_empleado' => ($i % 2 === 0 ? $e1 : $e2)->id,
                    'cantidad_entregada' => $cantidad,
                ]);
                if ($i === 2) {
                    $s->agotar($admin, $asignacion->id);
                }
            }
        }
    }
}
