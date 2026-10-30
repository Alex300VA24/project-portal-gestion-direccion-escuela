<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(
            database_path('gestion_horarios/03_restricciones_indices.sql')
        ));

        DB::unprepared(file_get_contents(
            database_path('gestion_horarios/04_comentarios.sql')
        ));
    }

    public function down(): void
    {
        $triggers = [
            'trg_validacion_horario' => 'validacion_horario',
            'trg_conflicto_horario' => 'conflicto_horario',
            'trg_transicion_horario' => 'horario',
            'trg_validar_detalle_horario' => 'detalle_horario',
            'trg_transicion_apertura' => 'apertura_repeticion',
        ];

        foreach ($triggers as $trigger => $tabla) {
            DB::statement("DROP TRIGGER IF EXISTS {$trigger} ON gestion_horarios.{$tabla}");
        }

        DB::statement(
            'ALTER TABLE gestion_horarios.asignacion_docente ' .
            'DROP CONSTRAINT IF EXISTS fk_asignacion_apertura'
        );

        DB::statement(
            'ALTER TABLE gestion_horarios.disponibilidad_docente ' .
            'DROP CONSTRAINT IF EXISTS fk_disponibilidad_declaracion'
        );

        $sql = file_get_contents(
            database_path('gestion_horarios/03_restricciones_indices.sql')
        );

        preg_match_all(
            '/\bCREATE\s+(?:UNIQUE\s+)?INDEX\s+([a-z_][a-z0-9_]*)\s+ON\s+gestion_horarios\./i',
            $sql,
            $coincidencias
        );

        foreach ($coincidencias[1] as $indice) {
            DB::statement("DROP INDEX IF EXISTS gestion_horarios.{$indice}");
        }

        foreach ([
            'fn_transicion_apertura()',
            'fn_validar_detalle_horario()',
            'fn_transicion_horario()',
            'fn_conflicto_horario()',
            'fn_validacion_horario()',
            'fn_huella_horario(BIGINT)',
        ] as $funcion) {
            DB::statement("DROP FUNCTION IF EXISTS gestion_horarios.{$funcion}");
        }
    }
};