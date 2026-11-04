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

        Schema::create('core.periodo_academico', function (Blueprint $table) {
            $table->bigIncrements('id_periodo');
            $table->string('codigo', 20)->unique('core_periodo_codigo_uq');
            $table->string('nombre', 100);
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->string('estado', 30);
        });

        Schema::create('core.estudiante', function (Blueprint $table) {
            $table->bigIncrements('id_estudiante');
            $table->string('codigo_estudiante', 30)->unique('core_estudiante_codigo_uq');
            $table->string('estado', 30);
            $table->timestampTz('creado_en')->useCurrent();
        });

        Schema::create('core.curso_plan', function (Blueprint $table) {
            $table->bigIncrements('id_curso_plan');
            $table->string('codigo', 50)->unique('core_curso_plan_codigo_uq');
            $table->smallInteger('ciclo')->nullable();
            $table->decimal('creditos', 5, 2)->nullable();
            $table->string('estado', 30);
        });

        Schema::create('core.seccion', function (Blueprint $table) {
            $table->bigIncrements('id_seccion');
            $table->bigInteger('id_periodo');
            $table->bigInteger('id_curso_plan');
            $table->string('codigo', 30);
            $table->integer('capacidad')->nullable();
            $table->string('estado', 30);
            $table->unique(
                ['id_periodo', 'id_curso_plan', 'codigo'],
                'core_seccion_periodo_curso_codigo_uq',
            );
            $table->index('id_curso_plan', 'core_seccion_curso_idx');
            $table->foreign('id_periodo', 'core_seccion_periodo_fk')
                ->references('id_periodo')
                ->on('core.periodo_academico')
                ->restrictOnDelete();
            $table->foreign('id_curso_plan', 'core_seccion_curso_fk')
                ->references('id_curso_plan')
                ->on('core.curso_plan')
                ->restrictOnDelete();
        });

        Schema::create('seguridad.usuario', function (Blueprint $table) {
            $table->bigIncrements('id_usuario');
            $table->string('correo_institucional', 190)->unique('seg_usuario_correo_uq');
            $table->string('contrasena_hash');
            $table->string('estado', 30);
            $table->rememberToken();
            $table->timestampTz('creado_en')->useCurrent();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE core.periodo_academico
            ADD CONSTRAINT core_periodo_fechas_ck
            CHECK (fecha_fin >= fecha_inicio)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE core.curso_plan
            ADD CONSTRAINT core_curso_plan_ciclo_ck
            CHECK (ciclo IS NULL OR ciclo > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE core.curso_plan
            ADD CONSTRAINT core_curso_plan_creditos_ck
            CHECK (creditos IS NULL OR creditos >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE core.seccion
            ADD CONSTRAINT core_seccion_capacidad_ck
            CHECK (capacidad IS NULL OR capacidad >= 0)
            SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        Schema::dropIfExists('seguridad.usuario');
        Schema::dropIfExists('core.seccion');
        Schema::dropIfExists('core.curso_plan');
        Schema::dropIfExists('core.estudiante');
        Schema::dropIfExists('core.periodo_academico');
    }
};
