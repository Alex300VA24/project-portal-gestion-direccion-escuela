<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** Filas creadas con el formato anterior después del primer mapeo. */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("UPDATE bienestar.solicitud_apoyo SET estado = lower(estado)
            WHERE estado <> lower(estado) AND lower(estado) IN ('enviada', 'recibida', 'observada', 'remitida', 'aprobada', 'rechazada')");
        DB::statement("UPDATE bienestar.solicitud_apoyo SET id_tipo_solicitud = t.id_tipo_solicitud
            FROM bienestar.tipo_solicitud t
            WHERE bienestar.solicitud_apoyo.id_tipo_solicitud IS NULL AND t.nombre = 'General (migrado)'");
    }

    public function down(): void
    {
        // El formato anterior no se restaura
    }
};
