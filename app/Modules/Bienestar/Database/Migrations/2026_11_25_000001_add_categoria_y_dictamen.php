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

        DB::statement('ALTER TABLE bienestar.alerta_riesgo ADD COLUMN IF NOT EXISTS categoria_motivo varchar(30)');
        DB::statement('ALTER TABLE bienestar.solicitud_apoyo ADD COLUMN IF NOT EXISTS observacion_dictamen text');

        // Secretaría ve la categoría, nunca el motivo redactado
        DB::statement("UPDATE bienestar.alerta_riesgo SET categoria_motivo = CASE
            WHEN regla LIKE 'inasistencia%' THEN 'asistencia'
            WHEN origen = 'automatica' THEN 'academico'
            ELSE 'otro' END WHERE categoria_motivo IS NULL");

        // El tipo migrado solo conserva historial: no se ofrece a los estudiantes
        DB::table('bienestar.tipo_solicitud')->where('nombre', 'General (migrado)')->update(['activo' => false]);

        // Estados históricos que el primer mapeo no contemplaba
        DB::statement("UPDATE bienestar.solicitud_apoyo SET estado = 'recibida'
            WHERE lower(replace(estado, ' ', '_')) IN ('en_evaluacion', 'en_revision', 'en_proceso')");
        // El folio solo está confirmado desde la remisión
        DB::statement("UPDATE bienestar.solicitud_apoyo SET folio_externo_confirmado = false
            WHERE estado IN ('enviada', 'recibida', 'observada')");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE bienestar.alerta_riesgo DROP COLUMN IF EXISTS categoria_motivo');
        DB::statement('ALTER TABLE bienestar.solicitud_apoyo DROP COLUMN IF EXISTS observacion_dictamen');
    }
};
