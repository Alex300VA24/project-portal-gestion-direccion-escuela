<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.consolidacion_vacantes (
    id_consolidacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_periodo BIGINT NOT NULL REFERENCES core.periodo_academico(id_periodo) ON DELETE RESTRICT,
    id_plan BIGINT NOT NULL REFERENCES core.plan_estudios(id_plan) ON DELETE RESTRICT,
    id_demanda BIGINT NOT NULL REFERENCES gestion_horarios.demanda_instantanea(id_demanda) ON DELETE RESTRICT,
    huella CHAR(64) NOT NULL,
    detalle JSONB NOT NULL,
    id_usuario BIGINT NOT NULL,
    consolidado_en TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX consolidacion_vacantes_vigente_idx ON gestion_horarios.consolidacion_vacantes(id_periodo, id_plan, id_consolidacion DESC);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE gestion_horarios.consolidacion_vacantes');
    }
};
