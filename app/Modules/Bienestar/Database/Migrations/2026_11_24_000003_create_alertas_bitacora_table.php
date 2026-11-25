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

        Schema::create('bienestar.alerta_bitacora', function (Blueprint $table) {
            $table->bigIncrements('id_bitacora');
            $table->bigInteger('id_alerta');
            $table->bigInteger('id_usuario');
            $table->string('accion', 50); // 'pasado_a_bienestar', 'descartado', 'reabierto'
            $table->text('motivo')->nullable(); // Obligatorio al descartar o reabrir
            $table->timestampTz('creado_en')->useCurrent();

            $table->foreign('id_alerta', 'bie_bitacora_alerta_fk')
                ->references('id_alerta')->on('bienestar.alerta_riesgo')
                ->onDelete('cascade');

            $table->foreign('id_usuario', 'bie_bitacora_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->onDelete('restrict');

            $table->index('id_alerta', 'bie_bitacora_alerta_idx');
            $table->index('id_usuario', 'bie_bitacora_usuario_idx');
            $table->index('accion', 'bie_bitacora_accion_idx');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('bienestar.alerta_bitacora');
    }
};
