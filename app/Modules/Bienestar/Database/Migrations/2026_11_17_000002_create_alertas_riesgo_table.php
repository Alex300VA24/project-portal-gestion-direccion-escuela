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

        Schema::create('bienestar.alerta_riesgo', function (Blueprint $table) {
            $table->bigIncrements('id_alerta');
            $table->bigInteger('id_estudiante');
            $table->string('periodo', 30);
            $table->string('curso', 150)->default('');
            $table->string('regla', 80);
            $table->string('origen', 40); // 'automatica', 'reporte_docente', 'manual_bienestar'
            $table->string('prioridad', 30); // 'baja', 'media', 'alta', 'urgente'
            $table->string('estado', 40)->default('pendiente'); // 'pendiente', 'atendida', 'descartada', 'resuelta_automaticamente'
            $table->bigInteger('id_caso')->nullable();

            // Campos específicos para reporte docente
            $table->bigInteger('id_docente')->nullable();
            $table->string('motivo_reporte', 255)->nullable();
            $table->text('descripcion_reporte')->nullable(); // Cifrado AES-256 via cast encrypted

            $table->jsonb('detalles')->nullable();
            $table->timestampTz('fecha_deteccion')->useCurrent();
            $table->timestampTz('fecha_resolucion')->nullable();
            $table->bigInteger('atendido_por')->nullable();
            $table->timestamps();

            $table->foreign('id_estudiante', 'bie_alerta_estudiante_fk')
                ->references('id_estudiante')->on('core.estudiante')
                ->restrictOnDelete();

            $table->foreign('id_caso', 'bie_alerta_caso_fk')
                ->references('id_caso')->on('bienestar.caso_bienestar')
                ->nullOnDelete();

            $table->foreign('id_docente', 'bie_alerta_docente_fk')
                ->references('id_docente')->on('core.docente')
                ->nullOnDelete();

            $table->foreign('atendido_por', 'bie_alerta_atendido_fk')
                ->references('id_usuario')->on('seguridad.usuario')
                ->nullOnDelete();

            $table->index('id_estudiante', 'bie_alerta_estudiante_idx');
            $table->index('estado', 'bie_alerta_estado_idx');
            $table->index('prioridad', 'bie_alerta_prioridad_idx');
            $table->index('origen', 'bie_alerta_origen_idx');
            $table->index('periodo', 'bie_alerta_periodo_idx');
        });

        // Índice único parcial para alertas automáticas
        DB::statement("CREATE UNIQUE INDEX bie_alerta_automatica_uq ON bienestar.alerta_riesgo (id_estudiante, periodo, curso, regla) WHERE origen = 'automatica';");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS bienestar.bie_alerta_automatica_uq;');
        Schema::dropIfExists('bienestar.alerta_riesgo');
    }
};
