<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.entregable (
                id_entregable BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_seccion BIGINT NOT NULL,
                nombre VARCHAR(150) NOT NULL,
                descripcion TEXT,
                fecha_limite TIMESTAMPTZ NOT NULL,
                orden SMALLINT NOT NULL,
                id_rubrica BIGINT NOT NULL,
                CONSTRAINT entregable_rubrica_fk FOREIGN KEY (id_rubrica)
                    REFERENCES investigacion_formativa.rubrica (id_rubrica) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT entregable_seccion_orden_unique UNIQUE (id_seccion, orden),
                CONSTRAINT entregable_nombre_check CHECK (btrim(nombre) <> ''),
                CONSTRAINT entregable_orden_check CHECK (orden > 0)
            );
            CREATE INDEX entregable_rubrica_idx ON investigacion_formativa.entregable (id_rubrica);
            COMMENT ON COLUMN investigacion_formativa.entregable.id_seccion IS 'Referencia pendiente a core.seccion(id_seccion), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.entregable');
    }
};
