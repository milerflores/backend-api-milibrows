<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Origin', 'http://localhost');
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $original = config('database.connections.mysql.database');
        $database = str_ends_with($original, '_pruebas') ? $original : $original.'_pruebas';
        if (! preg_match('/^[a-zA-Z0-9_]+_pruebas$/', $database)) {
            throw new \RuntimeException('Nombre de base de pruebas inválido.');
        }
        DB::statement('CREATE DATABASE IF NOT EXISTS `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        config(['database.connections.mysql.database' => $database]);
        DB::purge('mysql');

        return $app;
    }
}
