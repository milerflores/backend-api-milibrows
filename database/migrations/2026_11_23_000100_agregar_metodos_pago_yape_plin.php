<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            ['codigo' => 'yape', 'nombre' => 'Yape', 'activo' => 1],
            ['codigo' => 'plin', 'nombre' => 'Plin', 'activo' => 1],
        ] as $metodo) {
            DB::table('metodos_pago')->updateOrInsert(
                ['codigo' => $metodo['codigo']],
                ['nombre' => $metodo['nombre'], 'activo' => $metodo['activo']],
            );
        }
    }

    public function down(): void
    {
        foreach (['yape', 'plin'] as $codigo) {
            $id = DB::table('metodos_pago')->where('codigo', $codigo)->value('id');
            if ($id && ! DB::table('cobros')->where('id_metodo_pago', $id)->exists()) {
                DB::table('metodos_pago')->where('id', $id)->delete();
            }
        }
    }
};
