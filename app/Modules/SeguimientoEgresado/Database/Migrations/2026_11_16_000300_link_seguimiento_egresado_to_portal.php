<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Enlaces opcionales con el resto del portal, sin cambiar el modelo del módulo:
 *  - usuario_interno.id_usuario_portal: cuenta de public.users con la que entra la Secretaría o el Director. La
 *    contraseña y los roles los administra el portal (Breeze + Spatie); esta tabla solo da identidad a las
 *    columnas de autoría (quién creó la campaña, quién registró un estado, etc.).
 *  - egresado.id_estudiante: estudiante de core, si el egresado ya existe allí. Es opcional: el padrón del módulo
 *    se importa por archivo y no depende de que la persona esté en core.
 * Ambas referencias apuntan a esquemas permitidos (public y core).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE seguimiento_egresado.usuario_interno
                ADD COLUMN id_usuario_portal BIGINT,
                ADD CONSTRAINT uq_usuario_interno_portal UNIQUE (id_usuario_portal),
                ADD CONSTRAINT fk_usuario_interno_portal FOREIGN KEY (id_usuario_portal)
                    REFERENCES public.users (id) ON DELETE SET NULL;

            ALTER TABLE seguimiento_egresado.egresado
                ADD COLUMN id_estudiante BIGINT,
                ADD CONSTRAINT uq_egresado_estudiante UNIQUE (id_estudiante),
                ADD CONSTRAINT fk_egresado_estudiante FOREIGN KEY (id_estudiante)
                    REFERENCES core.estudiante (id_estudiante) ON DELETE SET NULL;
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE seguimiento_egresado.egresado
                DROP CONSTRAINT IF EXISTS fk_egresado_estudiante,
                DROP CONSTRAINT IF EXISTS uq_egresado_estudiante,
                DROP COLUMN IF EXISTS id_estudiante;

            ALTER TABLE seguimiento_egresado.usuario_interno
                DROP CONSTRAINT IF EXISTS fk_usuario_interno_portal,
                DROP CONSTRAINT IF EXISTS uq_usuario_interno_portal,
                DROP COLUMN IF EXISTS id_usuario_portal;
            SQL);
    }
};
