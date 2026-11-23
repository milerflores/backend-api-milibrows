<?php

namespace Database\Soporte;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

final class ModeloMilibrows
{
    public static function sql(string $archivo): void
    {
        $buffer = '';
        $delimitador = ';';
        foreach (preg_split('/\R/', file_get_contents($archivo)) as $linea) {
            if (preg_match('/^\s*--/', $linea)) {
                continue;
            }
            if (preg_match('/^DELIMITER\s+(\S+)$/i', trim($linea), $m)) {
                if (trim($buffer) !== '') {
                    throw new RuntimeException('SQL incompleto antes de DELIMITER.');
                }
                $delimitador = $m[1];

                continue;
            }
            $buffer .= $linea."\n";
            if (str_ends_with(rtrim($buffer), $delimitador)) {
                $sentencia = trim(substr(rtrim($buffer), 0, -strlen($delimitador)));
                if ($sentencia !== '') {
                    DB::unprepared($sentencia);
                }
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            throw new RuntimeException('SQL incompleto al terminar '.$archivo);
        }
    }

    public static function quitarForanea(string $tabla, string $campo): void
    {
        foreach (Schema::getForeignKeys($tabla) as $fk) {
            if (in_array($campo, $fk['columns'], true)) {
                DB::statement('ALTER TABLE `'.$tabla.'` DROP FOREIGN KEY `'.$fk['name'].'`');
            }
        }
    }

    public static function quitarIndice(string $tabla, array $campos): void
    {
        foreach (Schema::getIndexes($tabla) as $indice) {
            if (! $indice['primary'] && $indice['columns'] === $campos) {
                DB::statement('ALTER TABLE `'.$tabla.'` DROP INDEX `'.$indice['name'].'`');
            }
        }
    }

    public static function ordenarColumnas(): void
    {
        $tecnicas = ['migrations', 'sessions', 'personal_access_tokens', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs'];
        foreach (Schema::getTableListing(DB::connection()->getDatabaseName(), schemaQualified: false) as $tabla) {
            if (in_array($tabla, $tecnicas, true)) {
                continue;
            }
            $actuales = Schema::getColumnListing($tabla);
            $pk = [];
            foreach (Schema::getIndexes($tabla) as $indice) {
                if ($indice['primary']) {
                    $pk = $indice['columns'];
                }
            }
            $foraneas = [];
            foreach (Schema::getForeignKeys($tabla) as $fk) {
                $foraneas = array_merge($foraneas, $fk['columns']);
            }
            $foraneas = array_values(array_filter($actuales, fn ($c) => in_array($c, $foraneas, true) && ! in_array($c, $pk, true)));
            $auditoria = array_values(array_intersect(['fecha_creacion', 'fecha_actualizacion'], $actuales));
            $estados = array_values(array_filter($actuales, fn ($c) => in_array($c, ['estado', 'estado_servicio', 'admite_reservas', 'visible_al_publico'], true)));
            $activo = in_array('activo', $actuales, true) ? ['activo'] : [];
            $principales = array_values(array_diff($actuales, $pk, $foraneas, $auditoria, $estados, $activo));
            $orden = array_merge($pk, $foraneas, $principales, $estados, $activo, $auditoria);
            if ($orden === $actuales) {
                continue;
            }
            $create = (array) DB::selectOne('SHOW CREATE TABLE `'.$tabla.'`');
            $definiciones = [];
            foreach (explode("\n", $create['Create Table']) as $linea) {
                if (preg_match('/^\s*`([^`]+)` (.*?)(?:,)?$/', $linea, $m)) {
                    $definiciones[$m[1]] = '`'.$m[1].'` '.rtrim($m[2], ',');
                }
            }
            $cambios = [];
            $anterior = null;
            foreach ($orden as $campo) {
                $cambios[] = 'MODIFY COLUMN '.$definiciones[$campo].($anterior ? ' AFTER `'.$anterior.'`' : ' FIRST');
                $anterior = $campo;
            }
            DB::statement('ALTER TABLE `'.$tabla.'` '.implode(', ', $cambios));
        }
    }
}
