<?php

use App\Models\User;
use App\Modules\Agenda\AgendaService;
use App\Modules\Caja\CajaService;
use App\Modules\Inventario\InventarioService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

// Worker of integration tests. Never accepts credentials or a production database.
require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $database, $modo, $idUsuario, $payload, $inicio] = $argv;
if (! preg_match('/^[a-zA-Z0-9_]+_pruebas$/', $database)) {
    exit(2);
}
config(['database.connections.mysql.database' => $database, 'queue.default' => 'sync', 'mail.default' => 'array', 'cache.default' => 'array']);
DB::purge('mysql');
$u = User::findOrFail($idUsuario);
$d = json_decode(base64_decode($payload), true, 512, JSON_THROW_ON_ERROR);
while (microtime(true) < (float) $inicio) {
    usleep(1000);
}
try {
    match ($modo) {
        'reserva' => $app->make(AgendaService::class)->reservar($u, $d),
        'cobro' => $app->make(CajaService::class)->cobrar($u, $d),
        'stock' => $app->make(InventarioService::class)->entregar($u, $d),
    };
    echo json_encode(['estado' => 201]);
} catch (HttpExceptionInterface $e) {
    echo json_encode(['estado' => $e->getStatusCode()]);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage());
    exit(1);
}
