<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.proyecto (
                id_proyecto BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                id_grupo BIGINT NOT NULL UNIQUE,
                id_linea BIGINT NOT NULL,
                id_tipo_producto BIGINT NOT NULL,
                titulo VARCHAR(255) NOT NULL,
                resumen TEXT,
                estado VARCHAR(30) NOT NULL,
                registrado_en TIMESTAMPTZ NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT proyecto_grupo_fk FOREIGN KEY (id_grupo)
                    REFERENCES investigacion_formativa.grupo (id_grupo) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT proyecto_tipo_producto_fk FOREIGN KEY (id_tipo_producto)
                    REFERENCES investigacion_formativa.tipo_producto (id_tipo_producto) ON UPDATE RESTRICT ON DELETE RESTRICT,
                CONSTRAINT proyecto_titulo_check CHECK (btrim(titulo) <> ''),
                CONSTRAINT proyecto_estado_check CHECK (btrim(estado) <> '')
            );
            CREATE INDEX proyecto_linea_idx ON investigacion_formativa.proyecto (id_linea);
            CREATE INDEX proyecto_tipo_producto_idx ON investigacion_formativa.proyecto (id_tipo_producto);
            COMMENT ON COLUMN investigacion_formativa.proyecto.id_linea IS 'Referencia pendiente a core.linea_investigacion(id_linea), sin FK externa';
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.proyecto');
    }
};
