<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifica la estructura del esquema gestion_horarios en PostgreSQL:
 * 15 tablas, claves primarias, destinos permitidos de las claves
 * foráneas y comentarios de catálogo de las cuatro tablas nuevas.
 *
 * Las pruebas de reglas de negocio (rechazos) viven en
 * database/gestion_horarios/05_pruebas_integridad.sql.
 */
final class GestionHorariosDatabaseTest extends TestCase
{
    private const TABLAS = [
        'oferta_curso',
        'actividad_academica',
        'asignacion_docente',
        'bloque_horario',
        'disponibilidad_docente',
        'ambiente_bloqueo',
        'horario',
        'detalle_horario',
        'restriccion',
        'detalle_restriccion',
        'horario_revision',
        'apertura_repeticion',
        'declaracion_disponibilidad',
        'validacion_horario',
        'conflicto_horario',
    ];

    private const TABLAS_NUEVAS = [
        'apertura_repeticion',
        'declaracion_disponibilidad',
        'validacion_horario',
        'conflicto_horario',
    ];

    public function test_the_module_has_exactly_fifteen_tables(): void
    {
        $this->requirePostgres();

        $tablas = DB::select(
            "SELECT table_name FROM information_schema.tables
              WHERE table_schema = 'gestion_horarios' AND table_type = 'BASE TABLE'
              ORDER BY table_name"
        );

        $actual = array_map(fn ($fila) => $fila->table_name, $tablas);
        $esperado = self::TABLAS;
        sort($actual);
        sort($esperado);

        $this->assertSame($esperado, $actual);
    }

    public function test_every_table_has_a_primary_key(): void
    {
        $this->requirePostgres();

        $filas = DB::select(
            "SELECT count(*) AS total
               FROM information_schema.table_constraints
              WHERE table_schema = 'gestion_horarios' AND constraint_type = 'PRIMARY KEY'"
        );

        $this->assertSame(15, (int) $filas[0]->total);
    }

    public function test_foreign_keys_only_point_to_allowed_schemas(): void
    {
        $this->requirePostgres();

        $filas = DB::select(
            "SELECT count(*) AS total
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace tn ON tn.oid = t.relnamespace
               JOIN pg_class f ON f.oid = c.confrelid
               JOIN pg_namespace fn ON fn.oid = f.relnamespace
              WHERE c.contype = 'f'
                AND tn.nspname = 'gestion_horarios'
                AND fn.nspname NOT IN ('gestion_horarios', 'core', 'seguridad', 'comun', 'public')"
        );

        $this->assertSame(0, (int) $filas[0]->total);
    }

    public function test_the_four_new_tables_are_commented_in_the_catalog(): void
    {
        $this->requirePostgres();

        $comentadas = [];

        foreach (self::TABLAS_NUEVAS as $tabla) {
            $existe = DB::selectOne(
                "SELECT obj_description(?::regclass) IS NOT NULL AS ok",
                ["gestion_horarios.{$tabla}"]
            );

            if ($existe?->ok) {
                $comentadas[] = $tabla;
            }
        }

        sort($comentadas);

        $this->assertSame(self::TABLAS_NUEVAS, $comentadas);
    }

    public function test_shared_tables_of_other_modules_remain_untouched(): void
    {
        $this->requirePostgres();

        $filas = DB::select(
            "SELECT count(*) AS total
               FROM information_schema.tables
              WHERE table_schema IN (
                    'pre_matricula', 'bienestar', 'investigacion_formativa',
                    'seguimiento_estudiante', 'web_institucional', 'calidad',
                    'cotecu', 'gestion_practicas', 'tesis_proyecto_tesis',
                    'gestion_matriculas', 'seguimiento_egresado')"
        );

        $this->assertSame(0, (int) $filas[0]->total);
    }

    public function test_original_unique_constraints_are_preserved(): void
    {
        $this->requirePostgres();

        $esperadas = [
            'uq_oferta_curso',
            'uq_bloque_periodo_inicio',
            'uq_disponibilidad',
            'uq_detalle_horario_actividad',
            'uq_horario_version',
            'uq_restriccion_codigo',
            'uq_horario_revision_numero',
            'uq_declaracion_periodo_docente',
        ];

        $filas = DB::select(
            'SELECT DISTINCT c.conname AS nombre
               FROM pg_constraint c
               JOIN pg_class t ON t.oid = c.conrelid
               JOIN pg_namespace n ON n.oid = t.relnamespace
              WHERE n.nspname = \'gestion_horarios\'
                AND c.conname IN ('.implode(', ', array_fill(0, count($esperadas), '?')).')',
            $esperadas
        );

        $obtenidas = array_map(fn ($fila) => $fila->nombre, $filas);
        sort($obtenidas);
        sort($esperadas);

        $this->assertSame($esperadas, $obtenidas);
    }

    private function requirePostgres(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere una conexión PostgreSQL migrada.');
        }
    }
}
