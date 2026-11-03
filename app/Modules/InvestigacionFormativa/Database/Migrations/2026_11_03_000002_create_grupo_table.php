<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.grupo (
                id_grupo BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_seccion BIGINT NOT NULL,
                nombre VARCHAR(100) NOT NULL,
                CONSTRAINT grupo_seccion_nombre_unique UNIQUE (id_seccion, nombre),
                CONSTRAINT grupo_nombre_check CHECK (btrim(nombre) <> '')
            );
            COMMENT ON COLUMN investigacion_formativa.grupo.id_seccion IS 'Referencia pendiente a core.seccion(id_seccion), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.grupo');
    }
};
