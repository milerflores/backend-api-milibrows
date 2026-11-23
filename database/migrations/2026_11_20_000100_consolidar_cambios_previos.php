<?php

use Database\Soporte\ModeloMilibrows;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/../soporte/ModeloMilibrows.php';

return new class extends Migration
{
    public function up(): void
    {
        // Permite reproducir desde cero los cambios SQL acordados anteriormente.
        $pasos = [
            '2026_11_19_simplificar_cobros_y_notificaciones.sql' => Schema::hasTable('turnos_caja'),
            '2026_11_19_inventario_por_unidades.sql' => Schema::hasTable('unidades_medida'),
            '2026_11_19_fidelizacion_por_puntos.sql' => ! Schema::hasTable('movimientos_puntos'),
            '2026_11_20_simplificar_autoria_inventario.sql' => Schema::hasColumn('movimientos_inventario', 'clave_idempotencia'),
            '2026_11_20_ajustar_configuracion_usuarios_clientes_cobros.sql' => Schema::hasColumn('configuracion_estudio', 'zona_horaria'),
            '2026_11_20_eliminar_fecha_alta_fidelizacion.sql' => Schema::hasColumn('clientes', 'fecha_alta_fidelizacion'),
        ];
        foreach ($pasos as $archivo => $pendiente) {
            if ($pendiente) {
                ModeloMilibrows::sql(database_path('cambios/'.$archivo));
            }
        }
    }

    public function down(): void
    {
        throw new LogicException('La consolidación elimina datos históricos. Para revertir, restaure el respaldo o recree una base de prueba.');
    }
};
