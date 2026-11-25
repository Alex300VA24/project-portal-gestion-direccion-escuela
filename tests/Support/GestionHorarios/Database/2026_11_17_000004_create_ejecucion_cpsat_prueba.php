<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.ejecucion_cpsat_prueba (
    id UUID PRIMARY KEY,
    escenario VARCHAR(80) NOT NULL CHECK (escenario IN ('academico_factible_v1', 'academico_inviable_v1')),
    huella CHAR(64) NOT NULL,
    estado VARCHAR(20) NOT NULL CHECK (estado IN ('PENDIENTE','EN_COLA','EJECUTANDO','FINALIZADO','FALLIDO','CANCELADO')),
    solver VARCHAR(20) NULL CHECK (solver IS NULL OR solver IN ('OPTIMAL','FEASIBLE','INFEASIBLE','UNKNOWN','MODEL_INVALID')),
    validada BOOLEAN NULL,
    error_codigo VARCHAR(40) NULL,
    creada_en TIMESTAMPTZ NOT NULL DEFAULT now(),
    iniciada_en TIMESTAMPTZ NULL,
    finalizada_en TIMESTAMPTZ NULL
);
CREATE UNIQUE INDEX uq_ejecucion_prueba_activa ON gestion_horarios.ejecucion_cpsat_prueba(escenario) WHERE estado IN ('PENDIENTE','EN_COLA','EJECUTANDO');
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE gestion_horarios.ejecucion_cpsat_prueba');
    }
};
