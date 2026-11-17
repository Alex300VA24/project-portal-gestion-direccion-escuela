<?php

namespace Tests\Support\SeguimientoEgresado;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pruebas del módulo Seguimiento al Egresado contra PostgreSQL. El esquema lo crea `module:migrate` (no se carga
 * aquí): se omiten si la conexión no es pgsql o si las migraciones del módulo no se han aplicado. Cada prueba corre
 * en una transacción que se revierte al terminar, así que no deja datos.
 *
 * Con la conexión por defecto de phpunit.xml (SQLite) se omiten solas. Para correrlas:
 *   DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5433 DB_DATABASE=<base de pruebas migrada> DB_USERNAME=postgres \
 *   vendor/bin/phpunit tests/Feature/SeguimientoEgresado tests/Unit/SeguimientoEgresado
 * Usar una base distinta de la de trabajo: las pruebas del portal con RefreshDatabase recrean las tablas de `public`.
 */
trait UsaEsquemaPostgres
{
    protected function prepararPostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql' || ! Schema::hasTable('seguimiento_egresado.egresado')) {
            $this->markTestSkipped('Prueba con PostgreSQL: use DB_CONNECTION=pgsql y una base con `php artisan module:migrate all` aplicado.');
        }

        DB::beginTransaction();
    }

    protected function revertirPostgres(): void
    {
        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
    }
}
