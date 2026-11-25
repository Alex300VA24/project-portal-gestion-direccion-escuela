<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.ajuste_demanda (
    id_ajuste BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_demanda BIGINT NOT NULL,
    id_curso_plan BIGINT NOT NULL,
    total_anterior BIGINT NOT NULL CHECK (total_anterior >= 0),
    total_ajustado BIGINT NOT NULL CHECK (total_ajustado >= 0),
    motivo TEXT NOT NULL CHECK (length(trim(motivo)) > 0),
    id_usuario BIGINT NOT NULL,
    creado_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    FOREIGN KEY (id_demanda, id_curso_plan) REFERENCES gestion_horarios.demanda_curso(id_demanda, id_curso_plan) ON DELETE RESTRICT
);
CREATE INDEX ajuste_demanda_ultimo_idx ON gestion_horarios.ajuste_demanda(id_demanda, id_curso_plan, id_ajuste DESC);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE gestion_horarios.ajuste_demanda');
    }
};
