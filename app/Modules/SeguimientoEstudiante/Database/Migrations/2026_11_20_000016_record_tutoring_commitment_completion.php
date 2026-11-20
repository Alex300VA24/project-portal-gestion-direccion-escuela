<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.atencion_compromiso
                    ADD COLUMN cumplido_en TIMESTAMPTZ,
                    ADD COLUMN cumplido_por BIGINT REFERENCES public.users (id) ON DELETE SET NULL,
                    ADD CONSTRAINT se_compromiso_cumplimiento_ck
                        CHECK ((cumplido_en IS NULL AND cumplido_por IS NULL) OR (cumplido_en IS NOT NULL AND cumplido));
                SQL);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                ALTER TABLE seguimiento_estudiante.atencion_compromiso
                    DROP CONSTRAINT se_compromiso_cumplimiento_ck, DROP COLUMN cumplido_por, DROP COLUMN cumplido_en;
                SQL);
        }
    }
};
