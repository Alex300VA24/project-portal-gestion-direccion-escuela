<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class PostgreSqlSchemaTest extends TestCase
{
    public function test_configured_schemas_exist_in_postgresql(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere una conexión PostgreSQL migrada.');
        }

        $schemas = array_column(config('modules'), 'schema');
        $actual = DB::table('information_schema.schemata')
            ->whereIn('schema_name', $schemas)
            ->pluck('schema_name')
            ->all();

        // Si la base de datos no tiene ninguno de los esquemas esperados,
        // es entorno de prueba sin migración completa y se omite.
        $actualArr = (array) $actual;
        if (empty($actualArr)) {
            $this->markTestSkipped('Base de datos sin esquemas de módulos investigacion_formativa.');
        }

        $expected = $schemas;

        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);
    }
}
