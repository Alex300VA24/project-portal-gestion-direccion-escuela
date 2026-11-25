<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.tutoria_aviso ADD COLUMN clave_evento VARCHAR(100);
                CREATE UNIQUE INDEX se_tutoria_aviso_evento_uq ON seguimiento_estudiante.tutoria_aviso
                    (id_usuario, id_persona, clave_evento);
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP INDEX seguimiento_estudiante.se_tutoria_aviso_evento_uq;
                ALTER TABLE seguimiento_estudiante.tutoria_aviso DROP COLUMN clave_evento;
                SQL);
        }
    }
};
