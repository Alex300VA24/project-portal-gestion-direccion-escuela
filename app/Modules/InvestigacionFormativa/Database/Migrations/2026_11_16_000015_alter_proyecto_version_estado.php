<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const ESTADOS = ['Registrado', 'Aceptado', 'Cambio solicitado'];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $invalidos = DB::table('investigacion_formativa.proyecto')
            ->whereNotIn('estado', self::ESTADOS)
            ->distinct()
            ->pluck('estado')
            ->map(static fn (mixed $estado): string => (string) $estado)
            ->all();

        if ($invalidos !== []) {
            throw new RuntimeException(
                'Estados de proyecto no reconocidos: '.implode(', ', $invalidos)
                .'. Aplique un mapeo de datos explícito antes de esta migración.',
            );
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE investigacion_formativa.proyecto
                ADD COLUMN version INTEGER NOT NULL DEFAULT 1,
                ADD CONSTRAINT if_proyecto_version_ck CHECK (version > 0),
                ADD CONSTRAINT if_proyecto_estado_ck
                    CHECK (estado IN ('Registrado', 'Aceptado', 'Cambio solicitado'));
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
            ALTER TABLE investigacion_formativa.proyecto
                DROP CONSTRAINT IF EXISTS if_proyecto_estado_ck,
                DROP CONSTRAINT IF EXISTS if_proyecto_version_ck,
                DROP COLUMN IF EXISTS version;
            SQL);
    }
};
