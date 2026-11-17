<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $sinPadron = DB::table('investigacion_formativa.grupo_estudiante as ge')
            ->leftJoin(
                'investigacion_formativa.participante_seccion as ps',
                function ($join): void {
                    $join->on('ps.id_seccion', '=', 'ge.id_seccion')
                        ->on('ps.id_estudiante', '=', 'ge.id_estudiante');
                },
            )
            ->whereNull('ps.id_seccion')
            ->count();

        if ($sinPadron > 0) {
            throw new RuntimeException(
                "Hay {$sinPadron} integrantes de grupo sin fila en participante_seccion. "
                .'Importe el padrón autoritativo antes de aplicar esta migración.',
            );
        }

        DB::statement(<<<'SQL'
            ALTER TABLE investigacion_formativa.grupo_estudiante
                ADD CONSTRAINT if_grupo_estudiante_participante_fk
                    FOREIGN KEY (id_seccion, id_estudiante)
                    REFERENCES investigacion_formativa.participante_seccion (id_seccion, id_estudiante)
                    ON DELETE RESTRICT
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            ALTER TABLE investigacion_formativa.grupo_estudiante
                DROP CONSTRAINT IF EXISTS if_grupo_estudiante_participante_fk
            SQL);
    }
};
