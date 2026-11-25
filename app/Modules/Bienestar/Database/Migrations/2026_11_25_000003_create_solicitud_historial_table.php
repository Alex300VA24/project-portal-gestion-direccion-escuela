<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::create('bienestar.solicitud_historial', function (Blueprint $table) {
            $table->id('id_historial');
            $table->unsignedBigInteger('id_solicitud')->index();
            $table->string('estado_anterior', 30)->nullable();
            $table->string('estado_nuevo', 30);
            $table->unsignedBigInteger('cambiado_por')->nullable();
            $table->timestampTz('cambiado_en');
            $table->foreign('id_solicitud')->references('id_solicitud')->on('bienestar.solicitud_apoyo')->cascadeOnDelete();
        });

        // Punto de partida de las solicitudes que ya existían
        DB::statement("INSERT INTO bienestar.solicitud_historial (id_solicitud, estado_anterior, estado_nuevo, cambiado_en)
            SELECT id_solicitud, NULL, 'enviada', solicitada_en FROM bienestar.solicitud_apoyo");
        DB::statement("INSERT INTO bienestar.solicitud_historial (id_solicitud, estado_anterior, estado_nuevo, cambiado_en)
            SELECT id_solicitud, 'enviada', estado, coalesce(resuelta_en, fecha_remision_bienestar, fecha_revision_secretaria, solicitada_en)
            FROM bienestar.solicitud_apoyo WHERE estado <> 'enviada'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            Schema::dropIfExists('bienestar.solicitud_historial');
        }
    }
};
