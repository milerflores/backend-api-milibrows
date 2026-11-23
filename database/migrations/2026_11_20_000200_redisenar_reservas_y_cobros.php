<?php

use Database\Soporte\ModeloMilibrows;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/../soporte/ModeloMilibrows.php';

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('usuarios')->whereNull('id_persona')->get() as $usuario) {
            $id = DB::table('personas')->insertGetId(['nombre_completo' => $usuario->nombre, 'correo_contacto' => $usuario->correo, 'fecha_creacion' => now(), 'fecha_actualizacion' => now()]);
            DB::table('usuarios')->where('id', $usuario->id)->update(['id_persona' => $id]);
        }
        DB::statement('ALTER TABLE personas ADD COLUMN nombre VARCHAR(100) NULL, ADD COLUMN apellido_paterno VARCHAR(100) NULL, ADD COLUMN apellido_materno VARCHAR(100) NULL');
        foreach (DB::table('personas')->get() as $persona) {
            $texto = trim(preg_replace('/^\[DEMO\]\s*/u', '', $persona->nombre_completo));
            $partes = preg_split('/\s+/u', $texto, -1, PREG_SPLIT_NO_EMPTY);
            $materno = count($partes) >= 3 ? array_pop($partes) : null;
            // Los registros dummy sin apellido se conservan con un apellido de prueba.
            $paterno = count($partes) >= 2 ? array_pop($partes) : 'Prueba';
            $nombre = implode(' ', $partes) ?: 'Prueba';
            foreach ([$nombre, $paterno, $materno ?? ''] as $valor) {
                if (mb_strlen($valor) > 100) {
                    throw new RuntimeException('Un nombre supera los 100 caracteres.');
                }
            }
            DB::table('personas')->where('id', $persona->id)->update(['nombre' => $nombre, 'apellido_paterno' => $paterno, 'apellido_materno' => $materno]);
        }
        DB::statement('ALTER TABLE personas DROP COLUMN nombre_completo, MODIFY COLUMN nombre VARCHAR(100) NOT NULL, MODIFY COLUMN apellido_paterno VARCHAR(100) NOT NULL');
        ModeloMilibrows::quitarForanea('usuarios', 'id_persona');
        DB::statement('ALTER TABLE usuarios DROP COLUMN nombre, RENAME COLUMN version_acceso TO version_sesion, MODIFY COLUMN id_persona BIGINT UNSIGNED NOT NULL, ADD CONSTRAINT usuarios_id_persona_foreign FOREIGN KEY (id_persona) REFERENCES personas(id)');
        ModeloMilibrows::quitarForanea('servicios', 'id_categoria');
        DB::statement('ALTER TABLE servicios DROP COLUMN id_categoria');
        Schema::drop('especialidades_por_empleado');
        Schema::drop('especialidades');
        Schema::drop('categorias_servicios');

        DB::statement('ALTER TABLE citas ADD COLUMN fecha DATE NULL');
        DB::statement('UPDATE citas SET fecha = DATE(fecha_inicio)');
        DB::statement('ALTER TABLE servicios_cita ADD COLUMN id_empleado BIGINT UNSIGNED NULL, ADD COLUMN hora_inicio TIME NULL, ADD COLUMN fecha_inicio_real DATETIME NULL, ADD COLUMN fecha_finalizacion DATETIME NULL');
        foreach (DB::table('citas')->get() as $cita) {
            $inicio = new DateTimeImmutable($cita->fecha_inicio);
            $minutos = 0;
            foreach (DB::table('servicios_cita')->where('id_cita', $cita->id)->orderBy('orden')->orderBy('id')->get() as $servicio) {
                DB::table('servicios_cita')->where('id', $servicio->id)->update(['id_empleado' => $cita->id_empleado, 'hora_inicio' => $inicio->modify('+'.$minutos.' minutes')->format('H:i:s')]);
                $minutos += $servicio->duracion_minutos;
            }
        }
        DB::statement('ALTER TABLE servicios_cita ADD INDEX servicios_cita_cita_index (id_cita)');
        $indiceAnterior = ['id_cita', 'orden'];
        ModeloMilibrows::quitarIndice('servicios_cita', $indiceAnterior);
        // La FK de id_cita necesita un índice independiente antes de eliminar el UNIQUE.
        // En MySQL se crea si el índice compuesto era su único soporte.
        DB::statement('ALTER TABLE servicios_cita MODIFY COLUMN id_empleado BIGINT UNSIGNED NOT NULL, MODIFY COLUMN hora_inicio TIME NOT NULL, DROP COLUMN orden, RENAME COLUMN resultado TO estado_servicio, RENAME COLUMN motivo_resultado TO motivo_estado_servicio');
        DB::statement("ALTER TABLE servicios_cita MODIFY COLUMN estado_servicio ENUM('pendiente','en_proceso','realizado','cancelado','no_realizado') NOT NULL DEFAULT 'pendiente', ADD CONSTRAINT servicios_cita_empleado_foreign FOREIGN KEY (id_empleado) REFERENCES empleados(id), ADD INDEX servicios_cita_empleado_horario (id_empleado, hora_inicio, estado_servicio, id_cita), ADD CONSTRAINT servicios_cita_horario_valido CHECK (duracion_minutos > 0 AND TIME_TO_SEC(hora_inicio) >= 0 AND TIME_TO_SEC(hora_inicio) + duracion_minutos * 60 <= 86400), ADD CONSTRAINT servicios_cita_fechas_reales_validas CHECK (fecha_finalizacion IS NULL OR fecha_inicio_real IS NULL OR fecha_finalizacion >= fecha_inicio_real)");
        ModeloMilibrows::quitarForanea('citas', 'id_empleado');
        DB::statement('ALTER TABLE citas DROP INDEX citas_intervalo, DROP COLUMN id_empleado, DROP COLUMN fecha_inicio, DROP COLUMN fecha_fin, MODIFY COLUMN fecha DATE NOT NULL, RENAME COLUMN revision TO numero_revision, ADD INDEX citas_fecha_estado (fecha, estado)');
        ModeloMilibrows::quitarForanea('notificaciones', 'id_cliente');
        ModeloMilibrows::quitarForanea('notificaciones', 'id_evento_cita');
        DB::statement('ALTER TABLE notificaciones DROP COLUMN id_cliente, RENAME COLUMN id_evento_cita TO id_historial_cita');
        Schema::rename('eventos_cita', 'historial_cita');
        DB::statement('ALTER TABLE historial_cita RENAME COLUMN revision TO numero_revision, RENAME COLUMN tipo_evento TO tipo_cambio, RENAME COLUMN cambios TO detalle_cambios');
        DB::statement('ALTER TABLE historial_cita ADD UNIQUE INDEX historial_identidad_revision_unica (id, id_cita, numero_revision)');
        DB::statement('ALTER TABLE notificaciones ADD CONSTRAINT notificaciones_historial_cita_foreign FOREIGN KEY (id_historial_cita, id_cita, revision_cita) REFERENCES historial_cita(id, id_cita, numero_revision)');
        DB::statement('ALTER TABLE cobros ADD INDEX cobros_id_cita_index (id_cita), DROP INDEX cobros_id_cita_unique, ADD COLUMN referencia_pago VARCHAR(255) NULL, ADD CONSTRAINT cobros_importe_positivo CHECK (importe > 0)');
        DB::statement("UPDATE movimientos_inventario SET tipo_movimiento = 'salida' WHERE tipo_movimiento IN ('entrega', 'baja')");
        DB::statement("ALTER TABLE movimientos_inventario MODIFY COLUMN tipo_movimiento ENUM('entrada','salida','devolucion','ajuste') NOT NULL, ADD CONSTRAINT movimientos_inventario_saldos_validos CHECK (existencias_antes >= 0 AND existencias_despues >= 0 AND existencias_despues = existencias_antes + variacion_cantidad), ADD CONSTRAINT movimientos_inventario_signo_valido CHECK ((tipo_movimiento IN ('entrada','devolucion') AND variacion_cantidad > 0) OR (tipo_movimiento = 'salida' AND variacion_cantidad < 0) OR (tipo_movimiento = 'ajuste' AND variacion_cantidad <> 0))");
        ModeloMilibrows::ordenarColumnas();
        foreach ([
            'validar_horario_servicio',
            'validar_vinculo_notificacion',
            'validar_movimiento_asignado',
            'registrar_entrega_insumo',
            'registrar_cobro_parcial',
            'anular_cobro_con_puntos',
            'guardar_servicio_reservado',
            'reprogramar_fecha_cita',
        ] as $proc) {
            DB::unprepared('DROP PROCEDURE IF EXISTS `'.$proc.'`');
        }
        $reglas = database_path('cambios/2026_11_20_integridad_modelo_redisenado.sql');
        ModeloMilibrows::sql($reglas);
        $operaciones = database_path('cambios/2026_11_20_operaciones_modelo_redisenado.sql');
        ModeloMilibrows::sql($operaciones);
        ModeloMilibrows::sql(database_path('cambios/2026_11_20_integridad_cobros_redisenados.sql'));
    }

    public function down(): void
    {
        throw new LogicException('El rediseño elimina atributos y catálogos. Restaure el respaldo para recuperar el modelo anterior; use migrate:fresh únicamente en bases de prueba.');
    }
};
