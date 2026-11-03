<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE TABLE investigacion_formativa.tipo_producto (
                id_tipo_producto BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
                nombre VARCHAR(255) NOT NULL UNIQUE,
                CONSTRAINT tipo_producto_nombre_check CHECK (btrim(nombre) <> '')
            );
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TABLE IF EXISTS investigacion_formativa.tipo_producto');
    }
};
