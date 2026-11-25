<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.demanda_instantanea (
    id_demanda BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_periodo BIGINT NOT NULL CHECK (id_periodo > 0),
    version BIGINT NOT NULL CHECK (version > 0),
    huella CHAR(64) NOT NULL CHECK (huella ~ '^[0-9a-f]{64}$'),
    recibida_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    requiere_revision BOOLEAN NOT NULL DEFAULT false,
    UNIQUE (id_periodo, version),
    UNIQUE (id_demanda, id_periodo)
);
CREATE TABLE gestion_horarios.demanda_curso (
    id_demanda BIGINT NOT NULL REFERENCES gestion_horarios.demanda_instantanea(id_demanda) ON DELETE RESTRICT,
    id_curso_plan BIGINT NOT NULL CHECK (id_curso_plan > 0),
    total BIGINT NOT NULL CHECK (total >= 0),
    manana BIGINT NOT NULL CHECK (manana >= 0 AND manana <= total),
    tarde BIGINT NOT NULL CHECK (tarde >= 0 AND tarde <= total - manana),
    PRIMARY KEY (id_demanda, id_curso_plan)
);
ALTER TABLE gestion_horarios.horario ADD COLUMN id_demanda BIGINT NULL;
ALTER TABLE gestion_horarios.horario ADD CONSTRAINT fk_horario_demanda_periodo
    FOREIGN KEY (id_demanda, id_periodo)
    REFERENCES gestion_horarios.demanda_instantanea (id_demanda, id_periodo) ON DELETE RESTRICT;
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE gestion_horarios.horario DROP CONSTRAINT fk_horario_demanda_periodo');
        DB::statement('ALTER TABLE gestion_horarios.horario DROP COLUMN id_demanda');
        DB::statement('DROP TABLE gestion_horarios.demanda_curso');
        DB::statement('DROP TABLE gestion_horarios.demanda_instantanea');
    }
};
