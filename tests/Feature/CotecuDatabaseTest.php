<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CotecuDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL para probar los esquemas de COTECU.');
        }

        if (! DB::table('information_schema.tables')
            ->where('table_schema', 'cotecu')
            ->where('table_name', 'planes_curriculares')
            ->exists()) {
            $this->markTestSkipped('Las migraciones de COTECU aún no se ejecutaron.');
        }
    }

    public function test_cotecu_contains_the_ten_expected_domain_tables(): void
    {
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', 'cotecu')
            ->where('table_type', 'BASE TABLE')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->all();

        $this->assertSame([
            'lista_cotejo_items',
            'lista_cotejo_silabos_items',
            'listas_cotejo',
            'listas_cotejo_silabos',
            'planes_curriculares',
            'resultados_items',
            'resultados_silabos_items',
            'revisiones_plan_curricular',
            'revisiones_silabo',
            'silabos',
        ], $tables);
    }

    public function test_cotecu_does_not_duplicate_shared_catalogs_in_public(): void
    {
        $duplicates = DB::table('information_schema.tables')
            ->where('table_schema', 'public')
            ->whereIn('table_name', ['usuarios', 'cursos', 'semestres_academicos'])
            ->pluck('table_name')
            ->all();

        $this->assertSame([], $duplicates);
    }

    public function test_cotecu_references_shared_academic_and_security_tables(): void
    {
        $links = collect(DB::select(<<<'SQL'
            SELECT child.relname AS child_table,
                   child_column.attname AS child_column,
                   parent_schema.nspname AS parent_schema,
                   parent.relname AS parent_table,
                   parent_column.attname AS parent_column
            FROM pg_constraint constraint_definition
            JOIN pg_class child ON child.oid = constraint_definition.conrelid
            JOIN pg_namespace child_schema ON child_schema.oid = child.relnamespace
            JOIN pg_class parent ON parent.oid = constraint_definition.confrelid
            JOIN pg_namespace parent_schema ON parent_schema.oid = parent.relnamespace
            JOIN LATERAL unnest(constraint_definition.conkey) WITH ORDINALITY child_key(attnum, position)
                ON true
            JOIN LATERAL unnest(constraint_definition.confkey) WITH ORDINALITY parent_key(attnum, position)
                ON parent_key.position = child_key.position
            JOIN pg_attribute child_column
                ON child_column.attrelid = child.oid AND child_column.attnum = child_key.attnum
            JOIN pg_attribute parent_column
                ON parent_column.attrelid = parent.oid AND parent_column.attnum = parent_key.attnum
            WHERE constraint_definition.contype = 'f'
              AND child_schema.nspname = 'cotecu'
              AND parent_schema.nspname IN ('core', 'seguridad')
            ORDER BY child.relname, child_column.attname
            SQL))
            ->map(static fn (object $link): string => sprintf(
                '%s.%s->%s.%s.%s',
                $link->child_table,
                $link->child_column,
                $link->parent_schema,
                $link->parent_table,
                $link->parent_column,
            ))
            ->all();

        $this->assertSame([
            'planes_curriculares.id_plan->core.plan_estudios.id_plan',
            'revisiones_plan_curricular.id_revisor->seguridad.usuario.id_usuario',
            'revisiones_silabo.id_revisor->seguridad.usuario.id_usuario',
            'silabos.id_curso_plan->core.curso_plan.id_curso_plan',
            'silabos.id_periodo->core.periodo_academico.id_periodo',
            'silabos.id_profesor->seguridad.usuario.id_usuario',
        ], $links);
    }
}
