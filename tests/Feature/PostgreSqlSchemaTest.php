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

        $actual = DB::table('information_schema.schemata')
            ->whereIn('schema_name', array_column(config('modules'), 'schema'))
            ->pluck('schema_name')
            ->all();

        $expected = array_column(config('modules'), 'schema');

        sort($actual);
        sort($expected);

        $this->assertSame($expected, $actual);
    }
}
