<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
CREATE TABLE gestion_horarios.revision_oferta_pendiente (
    id_revision BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    id_oferta BIGINT NOT NULL REFERENCES gestion_horarios.oferta_curso(id_oferta) ON DELETE RESTRICT,
    id_usuario BIGINT NOT NULL,
    motivo TEXT NOT NULL,
    creada_en TIMESTAMPTZ NOT NULL DEFAULT now()
);
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE gestion_horarios.revision_oferta_pendiente');
    }
};
