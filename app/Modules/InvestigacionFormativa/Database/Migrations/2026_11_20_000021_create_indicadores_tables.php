<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Entradas de los indicadores 3.1 y 3.2 del módulo.
 *
 * `indicador_experiencia` declara, por curso del plan y periodo académico,
 * si la experiencia curricular aplica Investigación Formativa. El registro es
 * un dato de entrada reemplazable: la fuente académica autorizada debe
 * sustituir las filas de demostración.
 *
 * `configuracion_indicador` guarda los parámetros de cálculo, como la pregunta
 * de satisfacción y sus categorías. Las categorías son excluyentes entre sí y
 * cada valor pertenece a una sola categoría.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.indicador_experiencia (
                id_experiencia BIGSERIAL PRIMARY KEY,
                id_periodo BIGINT NOT NULL REFERENCES core.periodo_academico (id_periodo) ON DELETE RESTRICT,
                id_curso_plan BIGINT NOT NULL REFERENCES core.curso_plan (id_curso_plan) ON DELETE RESTRICT,
                id_escuela BIGINT NOT NULL REFERENCES core.escuela_profesional (id_escuela) ON DELETE RESTRICT,
                aplica_investigacion_formativa BOOLEAN NOT NULL,
                fuente VARCHAR(250) NOT NULL,
                CONSTRAINT if_indicador_experiencia_periodo_curso_uq
                    UNIQUE (id_periodo, id_curso_plan)
            );
            CREATE INDEX if_indicador_experiencia_escuela_periodo_idx
                ON investigacion_formativa.indicador_experiencia (id_escuela, id_periodo);

            CREATE TABLE investigacion_formativa.configuracion_indicador (
                id_configuracion BIGSERIAL PRIMARY KEY,
                indicador VARCHAR(10) NOT NULL,
                parametro VARCHAR(60) NOT NULL,
                valor TEXT NOT NULL,
                fuente VARCHAR(250) NOT NULL,
                provisional BOOLEAN NOT NULL DEFAULT true,
                CONSTRAINT if_configuracion_indicador_uq UNIQUE (indicador, parametro),
                CONSTRAINT if_configuracion_indicador_indicador_ck
                    CHECK (indicador IN ('3.1', '3.2', '3.3'))
            );
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.configuracion_indicador');
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.indicador_experiencia');
    }
};
