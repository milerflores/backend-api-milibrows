<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas', function (Blueprint $t) {
            $t->id();
            $t->string('dni', 8)->nullable()->unique();
            $t->string('nombre_completo');
            $t->string('telefono', 30)->nullable();
            $t->string('correo_contacto')->nullable();
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('id_persona')->nullable()->unique()->constrained('personas');
            $t->boolean('activo')->default(true);
            $t->unsignedInteger('version_acceso')->default(1);
            $t->text('two_factor_secret')->nullable();
            $t->text('two_factor_recovery_codes')->nullable();
            $t->timestamp('two_factor_confirmed_at')->nullable();
        });
        Schema::create('roles', function (Blueprint $t) {
            $t->id();
            $t->string('codigo')->unique();
            $t->string('nombre');
        });
        Schema::create('roles_usuario', function (Blueprint $t) {
            $t->foreignId('id_usuario')->constrained('users');
            $t->foreignId('id_rol')->constrained('roles');
            $t->primary(['id_usuario', 'id_rol']);
        });
        Schema::create('clientes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_persona')->unique()->constrained('personas');
            $t->text('preferencias')->nullable();
            $t->timestamp('fecha_alta_fidelizacion')->nullable();
            $t->timestamps();
        });
        Schema::create('empleados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_usuario')->unique()->constrained('users');
            $t->string('cargo');
            $t->boolean('activo')->default(true);
            $t->boolean('admite_reservas')->default(false);
            $t->timestamps();
        });
        Schema::create('configuracion_estudio', function (Blueprint $t) {
            $t->id();
            $t->string('nombre_estudio');
            $t->string('zona_horaria');
            $t->string('canal_notificacion')->nullable();
            $t->unsignedInteger('anticipacion_recordatorio_min')->default(1440);
        });
        Schema::create('horarios_estudio', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_estudio')->constrained('configuracion_estudio');
            $t->unsignedTinyInteger('dia_semana');
            $t->time('hora_inicio');
            $t->time('hora_fin');
        });
        Schema::create('jornadas_empleados', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_empleado')->constrained('empleados');
            $t->unsignedTinyInteger('dia_semana');
            $t->time('hora_inicio');
            $t->time('hora_fin');
        });
        Schema::create('categorias_servicios', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->boolean('activo')->default(true);
        });
        Schema::create('servicios', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_categoria')->constrained('categorias_servicios');
            $t->string('nombre');
            $t->decimal('precio', 12, 2);
            $t->unsignedSmallInteger('duracion_minutos');
            $t->boolean('activo')->default(true);
            $t->boolean('visible_al_publico')->default(true);
            $t->timestamps();
        });
        Schema::create('especialidades', function (Blueprint $t) {
            $t->id();
            $t->string('nombre')->unique();
            $t->boolean('activo')->default(true);
        });
        Schema::create('servicios_por_empleado', function (Blueprint $t) {
            $t->foreignId('id_empleado')->constrained('empleados');
            $t->foreignId('id_servicio')->constrained('servicios');
            $t->boolean('activo')->default(true);
            $t->primary(['id_empleado', 'id_servicio']);
        });
        Schema::create('especialidades_por_empleado', function (Blueprint $t) {
            $t->foreignId('id_empleado')->constrained('empleados');
            $t->foreignId('id_especialidad')->constrained('especialidades');
            $t->primary(['id_empleado', 'id_especialidad']);
        });
        Schema::create('citas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_cliente')->constrained('clientes');
            $t->foreignId('id_empleado')->constrained('empleados');
            $t->foreignId('id_usuario_creador')->constrained('users');
            $t->dateTime('fecha_inicio');
            $t->dateTime('fecha_fin');
            $t->dateTime('fecha_finalizacion')->nullable();
            $t->enum('estado', ['confirmada', 'completada', 'cancelada', 'no_asistio'])->default('confirmada');
            $t->string('origen');
            $t->unsignedInteger('revision')->default(1);
            $t->text('motivo_cancelacion')->nullable();
            $t->timestamps();
            $t->index(['id_empleado', 'estado', 'fecha_inicio', 'fecha_fin'], 'citas_intervalo');
        });
        Schema::create('eventos_cita', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_cita')->constrained('citas');
            $t->foreignId('id_usuario_responsable')->constrained('users');
            $t->unsignedInteger('revision');
            $t->string('tipo_evento');
            $t->json('cambios');
            $t->text('motivo')->nullable();
            $t->timestamp('fecha_creacion');
            $t->unique(['id_cita', 'revision']);
        });
        Schema::create('servicios_cita', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_cita')->constrained('citas');
            $t->foreignId('id_servicio')->constrained('servicios');
            $t->unsignedSmallInteger('orden');
            $t->string('nombre_servicio_acordado');
            $t->decimal('precio_acordado', 12, 2);
            $t->unsignedSmallInteger('duracion_minutos');
            $t->enum('resultado', ['pendiente', 'realizado', 'cancelado', 'no_realizado'])->default('pendiente');
            $t->text('motivo_resultado')->nullable();
            $t->unique(['id_cita', 'orden']);
        });
        Schema::create('notas_cita', function (Blueprint $t) {
            $t->foreignId('id_cita')->primary()->constrained('citas');
            $t->text('descripcion_tratamiento')->nullable();
            $t->text('observaciones')->nullable();
        });
        Schema::create('metodos_pago', function (Blueprint $t) {
            $t->id();
            $t->string('codigo')->unique();
            $t->string('nombre');
            $t->boolean('activo')->default(true);
        });
        Schema::create('cajas', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->boolean('activo')->default(true);
        });
        Schema::create('turnos_caja', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_caja')->constrained('cajas');
            $t->foreignId('id_usuario_apertura')->constrained('users');
            $t->foreignId('id_usuario_cierre')->nullable()->constrained('users');
            $t->dateTime('fecha_apertura');
            $t->dateTime('fecha_cierre')->nullable();
            $t->decimal('efectivo_inicial', 12, 2);
            $t->decimal('efectivo_final', 12, 2)->nullable();
        });
        Schema::create('cobros', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_cita')->unique()->constrained('citas');
            $t->foreignId('id_turno_caja')->constrained('turnos_caja');
            $t->foreignId('id_metodo_pago')->constrained('metodos_pago');
            $t->foreignId('id_usuario_creador')->constrained('users');
            $t->string('clave_idempotencia', 100)->unique();
            $t->decimal('importe', 12, 2);
            $t->dateTime('fecha_cobro');
            $t->string('estado')->default('registrado');
            $t->string('referencia')->nullable();
        });
        Schema::create('unidades_medida', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->string('simbolo');
        });
        Schema::create('categorias_inventario', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
        });
        Schema::create('insumos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_unidad_medida')->constrained('unidades_medida');
            $t->foreignId('id_categoria')->constrained('categorias_inventario');
            $t->string('nombre');
            $t->decimal('existencias_actuales', 12, 3)->default(0);
            $t->decimal('existencias_minimas', 12, 3)->default(0);
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
        Schema::create('asignaciones_insumos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_empleado')->constrained('empleados');
            $t->foreignId('id_insumo')->constrained('insumos');
            $t->foreignId('id_autor_entrega')->constrained('users');
            $t->foreignId('id_autor_agotamiento')->nullable()->constrained('users');
            $t->decimal('cantidad_entregada', 12, 3);
            $t->dateTime('fecha_entrega');
            $t->string('estado')->default('en_uso');
            $t->dateTime('fecha_agotamiento')->nullable();
        });
        Schema::create('movimientos_inventario', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_insumo')->constrained('insumos');
            $t->foreignId('id_usuario_creador')->constrained('users');
            $t->foreignId('id_asignacion')->nullable()->unique()->constrained('asignaciones_insumos');
            $t->string('clave_idempotencia', 100)->unique();
            $t->string('tipo_movimiento');
            $t->decimal('variacion_cantidad', 12, 3);
            $t->decimal('existencias_antes', 12, 3);
            $t->decimal('existencias_despues', 12, 3);
            $t->text('motivo');
            $t->timestamp('fecha_creacion');
        });
        Schema::create('programas_sellos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre');
            $t->unsignedInteger('sellos_requeridos');
            $t->text('descripcion_recompensa');
            $t->boolean('activo')->default(true);
        });
        Schema::create('servicios_por_programa', function (Blueprint $t) {
            $t->foreignId('id_programa')->constrained('programas_sellos');
            $t->foreignId('id_servicio')->constrained('servicios');
            $t->primary(['id_programa', 'id_servicio']);
        });
        Schema::create('sellos_obtenidos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_programa')->constrained('programas_sellos');
            $t->foreignId('id_servicio_cita')->constrained('servicios_cita');
            $t->timestamp('fecha_obtencion');
            $t->unique(['id_programa', 'id_servicio_cita']);
        });
        Schema::create('recompensas_sellos', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_programa')->constrained('programas_sellos');
            $t->foreignId('id_cliente')->constrained('clientes');
            $t->unsignedInteger('numero_ciclo');
            $t->text('descripcion_recompensa_emitida');
            $t->timestamp('fecha_emision');
            $t->timestamp('fecha_canje')->nullable();
            $t->unique(['id_programa', 'id_cliente', 'numero_ciclo'], 'recompensa_ciclo');
        });
        Schema::create('niveles_fidelizacion', function (Blueprint $t) {
            $t->id();
            $t->string('codigo')->unique();
            $t->unsignedTinyInteger('orden')->unique();
            $t->string('metrica');
            $t->decimal('umbral', 12, 2)->nullable();
            $t->text('descripcion_beneficio')->nullable();
        });
        Schema::create('notificaciones', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_cita')->constrained('citas');
            $t->foreignId('id_cliente')->constrained('clientes');
            $t->foreignId('id_evento_cita')->nullable()->constrained('eventos_cita');
            $t->string('clave_deduplicacion')->unique();
            $t->unsignedInteger('revision_cita');
            $t->string('tipo');
            $t->string('canal')->nullable();
            $t->dateTime('fecha_programada');
            $t->string('estado')->default('pendiente');
            $t->dateTime('fecha_envio')->nullable();
            $t->index(['estado', 'fecha_programada']);
        });
        Schema::create('intentos_notificacion', function (Blueprint $t) {
            $t->id();
            $t->foreignId('id_notificacion')->constrained('notificaciones');
            $t->unsignedInteger('numero_intento');
            $t->string('estado');
            $t->string('id_mensaje_proveedor')->nullable();
            $t->timestamp('fecha_creacion');
            $t->unique(['id_notificacion', 'numero_intento']);
        });
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->text('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable()->index();
            $t->timestamps();
        });
        // MySQL/InnoDB is the production and integration-test database.
        DB::table('roles')->insert([
            ['codigo' => 'administracion', 'nombre' => 'Administración'], ['codigo' => 'recepcion', 'nombre' => 'Recepción'],
            ['codigo' => 'estilista', 'nombre' => 'Estilista'], ['codigo' => 'cliente', 'nombre' => 'Cliente'],
        ]);
        DB::table('configuracion_estudio')->insert(['id' => 1, 'nombre_estudio' => 'MiliBrows Studio', 'zona_horaria' => 'America/Lima']);
        foreach (range(1, 6) as $dia) {
            DB::table('horarios_estudio')->insert(['id_estudio' => 1, 'dia_semana' => $dia, 'hora_inicio' => '10:00', 'hora_fin' => '22:00']);
        }
        DB::table('cajas')->insert(['nombre' => 'Caja principal', 'activo' => true]);
        foreach (['efectivo' => 'Efectivo', 'tarjeta' => 'Tarjeta', 'transferencia' => 'Transferencia'] as $codigo => $nombre) {
            DB::table('metodos_pago')->insert(compact('codigo', 'nombre'));
        }
        DB::table('unidades_medida')->insert(['nombre' => 'Unidad', 'simbolo' => 'u']);
        DB::table('categorias_inventario')->insert(['nombre' => 'General']);
        foreach (['grow' => 1, 'gold' => 2, 'diamond' => 3] as $codigo => $orden) {
            DB::table('niveles_fidelizacion')->insert([
                'codigo' => $codigo, 'orden' => $orden, 'metrica' => 'servicios', 'umbral' => $orden === 1 ? 1 : null,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['personal_access_tokens', 'intentos_notificacion', 'notificaciones', 'niveles_fidelizacion', 'recompensas_sellos', 'sellos_obtenidos', 'servicios_por_programa', 'programas_sellos', 'movimientos_inventario', 'asignaciones_insumos', 'insumos', 'categorias_inventario', 'unidades_medida', 'cobros', 'turnos_caja', 'cajas', 'metodos_pago', 'notas_cita', 'servicios_cita', 'eventos_cita', 'citas', 'especialidades_por_empleado', 'servicios_por_empleado', 'especialidades', 'servicios', 'categorias_servicios', 'jornadas_empleados', 'horarios_estudio', 'configuracion_estudio', 'empleados', 'clientes', 'roles_usuario', 'roles'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('id_persona');
            $t->dropColumn(['activo', 'version_acceso', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
        Schema::dropIfExists('personas');
    }
};
