<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.rubrica (
                id_rubrica BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre VARCHAR(150) NOT NULL,
                descripcion TEXT,
                id_docente_autor BIGINT NOT NULL,
                estado VARCHAR(30) NOT NULL,
                CONSTRAINT rubrica_nombre_check CHECK (btrim(nombre) <> ''),
                CONSTRAINT rubrica_estado_check CHECK (btrim(estado) <> '')
            );
            CREATE INDEX rubrica_docente_autor_idx ON investigacion_formativa.rubrica (id_docente_autor);
            COMMENT ON COLUMN investigacion_formativa.rubrica.id_docente_autor IS 'Referencia pendiente a core.docente(id_docente), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.rubrica');
    }
};
