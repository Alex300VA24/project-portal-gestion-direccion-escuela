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

        Schema::create('prematricula.cronograma_prematricula', function (Blueprint $table) {
            $table->bigIncrements('id_cronograma');
            $table->bigInteger('id_periodo');
            $table->timestampTz('fecha_inicio');
            $table->timestampTz('fecha_fin');
            $table->string('estado', 30);
            $table->unique('id_periodo', 'pre_crono_periodo_uq');
        });

        Schema::create('prematricula.solicitud_prematricula', function (Blueprint $table) {
            $table->bigIncrements('id_solicitud');
            $table->bigInteger('id_estudiante');
            $table->bigInteger('id_periodo');
            $table->string('estado', 30);
            $table->text('observacion')->nullable();
            $table->timestampTz('enviada_en')->nullable();
            $table->bigInteger('revisado_por')->nullable();
            $table->timestampTz('revisado_en')->nullable();
            $table->unique(['id_estudiante', 'id_periodo'], 'pre_sol_est_periodo_uq');
            $table->index('id_periodo', 'pre_sol_periodo_idx');
            $table->index('revisado_por', 'pre_sol_revisor_idx');
        });

        Schema::create('prematricula.detalle_solicitud', function (Blueprint $table) {
            $table->bigIncrements('id_detalle');
            $table->bigInteger('id_solicitud');
            $table->bigInteger('id_curso_plan');
            $table->bigInteger('id_seccion_preferida')->nullable();
            $table->string('estado', 30);
            $table->text('motivo_rechazo')->nullable();
            $table->unique(['id_solicitud', 'id_curso_plan'], 'pre_det_sol_curso_uq');
            $table->index('id_curso_plan', 'pre_det_curso_idx');
            $table->index('id_seccion_preferida', 'pre_det_sec_idx');
            $table->foreign('id_solicitud', 'pre_de_sol_fk')
                ->references('id_solicitud')
                ->on('prematricula.solicitud_prematricula')
                ->restrictOnDelete();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE prematricula.cronograma_prematricula
            ADD CONSTRAINT pre_crono_fechas_ck
            CHECK (fecha_fin > fecha_inicio)
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('prematricula.detalle_solicitud');
        Schema::dropIfExists('prematricula.solicitud_prematricula');
        Schema::dropIfExists('prematricula.cronograma_prematricula');
    }
};
