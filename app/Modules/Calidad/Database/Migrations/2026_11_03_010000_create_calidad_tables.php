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

        DB::statement('CREATE SCHEMA IF NOT EXISTS calidad');

        Schema::create('calidad.proceso', function (Blueprint $table) {
            $table->bigIncrements('id_proceso');
            $table->bigInteger('id_proceso_padre')->nullable();
            $table->string('codigo', 30)->unique('cal_proceso_codigo_uq');
            $table->string('nombre', 180);
            $table->smallInteger('nivel');
            $table->bigInteger('id_responsable');
            $table->index('id_proceso_padre', 'cal_proceso_padre_idx');
            $table->index('id_responsable', 'cal_proceso_responsable_idx');
            $table->foreign('id_proceso_padre', 'cal_proceso_padre_fk')
                ->references('id_proceso')->on('calidad.proceso')->restrictOnDelete();
            $table->foreign('id_responsable', 'cal_proceso_responsable_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('calidad.indicador', function (Blueprint $table) {
            $table->bigIncrements('id_indicador');
            $table->bigInteger('id_proceso');
            $table->string('codigo', 30)->unique('cal_indicador_codigo_uq');
            $table->string('nombre', 180);
            $table->text('objetivo');
            $table->text('formula');
            $table->string('unidad_medida', 60);
            $table->string('frecuencia', 40);
            $table->string('sentido', 20);
            $table->bigInteger('id_modulo_fuente')->nullable();
            $table->bigInteger('id_responsable');
            $table->string('estado', 20)->default('Activo');
            $table->index('id_proceso', 'cal_indicador_proceso_idx');
            $table->index('id_modulo_fuente', 'cal_indicador_modulo_idx');
            $table->index('id_responsable', 'cal_indicador_responsable_idx');
            $table->foreign('id_proceso', 'cal_indicador_proceso_fk')
                ->references('id_proceso')->on('calidad.proceso')->restrictOnDelete();
            $table->foreign('id_modulo_fuente', 'cal_indicador_modulo_fk')
                ->references('id_modulo')->on('seguridad.modulo_sistema')->restrictOnDelete();
            $table->foreign('id_responsable', 'cal_indicador_responsable_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('calidad.meta_indicador', function (Blueprint $table) {
            $table->bigIncrements('id_meta');
            $table->bigInteger('id_indicador');
            $table->bigInteger('id_periodo');
            $table->decimal('valor_meta', 14, 4);
            $table->decimal('umbral_minimo', 14, 4)->nullable();
            $table->unique(['id_indicador', 'id_periodo'], 'cal_meta_indicador_periodo_uq');
            $table->index('id_periodo', 'cal_meta_periodo_idx');
            $table->foreign('id_indicador', 'cal_meta_indicador_fk')
                ->references('id_indicador')->on('calidad.indicador')->cascadeOnDelete();
            $table->foreign('id_periodo', 'cal_meta_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
        });

        Schema::create('calidad.medicion_indicador', function (Blueprint $table) {
            $table->bigIncrements('id_medicion');
            $table->bigInteger('id_indicador');
            $table->bigInteger('id_periodo');
            $table->decimal('numerador', 18, 4);
            $table->decimal('denominador', 18, 4);
            $table->bigInteger('id_archivo_sustento')->nullable();
            $table->bigInteger('registrado_por');
            $table->timestampTz('registrado_en')->useCurrent();
            $table->unique(['id_indicador', 'id_periodo'], 'cal_medicion_indicador_periodo_uq');
            $table->index('id_periodo', 'cal_medicion_periodo_idx');
            $table->index('id_archivo_sustento', 'cal_medicion_archivo_idx');
            $table->index('registrado_por', 'cal_medicion_usuario_idx');
            $table->foreign('id_indicador', 'cal_medicion_indicador_fk')
                ->references('id_indicador')->on('calidad.indicador')->cascadeOnDelete();
            $table->foreign('id_periodo', 'cal_medicion_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
            $table->foreign('id_archivo_sustento', 'cal_medicion_archivo_fk')
                ->references('id_archivo')->on('comun.archivo')->restrictOnDelete();
            $table->foreign('registrado_por', 'cal_medicion_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('calidad.modelo_acreditacion', function (Blueprint $table) {
            $table->bigIncrements('id_modelo');
            $table->string('nombre', 150);
            $table->string('version', 40);
            $table->unique(['nombre', 'version'], 'cal_modelo_nombre_version_uq');
        });

        Schema::create('calidad.dimension_acreditacion', function (Blueprint $table) {
            $table->bigIncrements('id_dimension');
            $table->bigInteger('id_modelo');
            $table->string('codigo', 30);
            $table->string('nombre', 180);
            $table->unique(['id_modelo', 'codigo'], 'cal_dimension_modelo_codigo_uq');
            $table->foreign('id_modelo', 'cal_dimension_modelo_fk')
                ->references('id_modelo')->on('calidad.modelo_acreditacion')->cascadeOnDelete();
        });

        Schema::create('calidad.estandar', function (Blueprint $table) {
            $table->bigIncrements('id_estandar');
            $table->bigInteger('id_dimension');
            $table->string('codigo', 30);
            $table->string('nombre', 180);
            $table->text('descripcion')->nullable();
            $table->unique(['id_dimension', 'codigo'], 'cal_estandar_dimension_codigo_uq');
            $table->foreign('id_dimension', 'cal_estandar_dimension_fk')
                ->references('id_dimension')->on('calidad.dimension_acreditacion')->cascadeOnDelete();
        });

        Schema::create('calidad.indicador_estandar', function (Blueprint $table) {
            $table->bigInteger('id_indicador');
            $table->bigInteger('id_estandar');
            $table->primary(['id_indicador', 'id_estandar'], 'cal_indicador_estandar_pk');
            $table->index('id_estandar', 'cal_indicador_estandar_estandar_idx');
            $table->foreign('id_indicador', 'cal_indicador_estandar_indicador_fk')
                ->references('id_indicador')->on('calidad.indicador')->cascadeOnDelete();
            $table->foreign('id_estandar', 'cal_indicador_estandar_estandar_fk')
                ->references('id_estandar')->on('calidad.estandar')->cascadeOnDelete();
        });

        Schema::create('calidad.evidencia', function (Blueprint $table) {
            $table->bigIncrements('id_evidencia');
            $table->bigInteger('id_estandar');
            $table->bigInteger('id_periodo');
            $table->string('titulo', 180);
            $table->text('descripcion')->nullable();
            $table->bigInteger('id_archivo');
            $table->bigInteger('registrado_por');
            $table->timestampTz('registrado_en')->useCurrent();
            $table->index('id_periodo', 'cal_evidencia_periodo_idx');
            $table->index('id_archivo', 'cal_evidencia_archivo_idx');
            $table->index('registrado_por', 'cal_evidencia_usuario_idx');
            $table->foreign('id_estandar', 'cal_evidencia_estandar_fk')
                ->references('id_estandar')->on('calidad.estandar')->restrictOnDelete();
            $table->foreign('id_periodo', 'cal_evidencia_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
            $table->foreign('id_archivo', 'cal_evidencia_archivo_fk')
                ->references('id_archivo')->on('comun.archivo')->restrictOnDelete();
            $table->foreign('registrado_por', 'cal_evidencia_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('calidad.plan_mejora', function (Blueprint $table) {
            $table->bigIncrements('id_plan_mejora');
            $table->bigInteger('id_indicador')->nullable();
            $table->bigInteger('id_estandar')->nullable();
            $table->bigInteger('id_periodo');
            $table->string('titulo', 180);
            $table->text('descripcion');
            $table->bigInteger('id_responsable');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado', 30)->default('Planificado');
            $table->index('id_indicador', 'cal_plan_indicador_idx');
            $table->index('id_estandar', 'cal_plan_estandar_idx');
            $table->index('id_periodo', 'cal_plan_periodo_idx');
            $table->index('id_responsable', 'cal_plan_responsable_idx');
            $table->foreign('id_indicador', 'cal_plan_indicador_fk')
                ->references('id_indicador')->on('calidad.indicador')->restrictOnDelete();
            $table->foreign('id_estandar', 'cal_plan_estandar_fk')
                ->references('id_estandar')->on('calidad.estandar')->restrictOnDelete();
            $table->foreign('id_periodo', 'cal_plan_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
            $table->foreign('id_responsable', 'cal_plan_responsable_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('calidad.accion_mejora', function (Blueprint $table) {
            $table->bigIncrements('id_accion');
            $table->bigInteger('id_plan_mejora');
            $table->text('descripcion');
            $table->bigInteger('id_responsable');
            $table->date('fecha_limite');
            $table->decimal('avance_porcentaje', 5, 2)->default(0);
            $table->string('estado', 30)->default('Pendiente');
            $table->index('id_responsable', 'cal_accion_responsable_idx');
            $table->foreign('id_plan_mejora', 'cal_accion_plan_fk')
                ->references('id_plan_mejora')->on('calidad.plan_mejora')->cascadeOnDelete();
            $table->foreign('id_responsable', 'cal_accion_responsable_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('calidad.comite_calidad_miembro', function (Blueprint $table) {
            $table->bigIncrements('id_miembro');
            $table->bigInteger('id_persona');
            $table->string('cargo', 100);
            $table->string('estamento', 60);
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->index('id_persona', 'cal_comite_persona_idx');
            $table->foreign('id_persona', 'cal_comite_persona_fk')
                ->references('id_persona')->on('core.persona')->restrictOnDelete();
        });

        $this->addChecks();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['comite_calidad_miembro', 'accion_mejora', 'plan_mejora', 'evidencia',
            'indicador_estandar', 'estandar', 'dimension_acreditacion', 'modelo_acreditacion',
            'medicion_indicador', 'meta_indicador', 'indicador', 'proceso'] as $table) {
            Schema::dropIfExists("calidad.{$table}");
        }
    }

    private function addChecks(): void
    {
        DB::statement('ALTER TABLE calidad.proceso ADD CONSTRAINT cal_proceso_nivel_ck CHECK (nivel > 0)');
        DB::statement("ALTER TABLE calidad.indicador ADD CONSTRAINT cal_indicador_sentido_ck CHECK (sentido IN ('Ascendente', 'Descendente', 'Objetivo'))");
        DB::statement('ALTER TABLE calidad.medicion_indicador ADD CONSTRAINT cal_medicion_denominador_ck CHECK (denominador >= 0)');
        DB::statement('ALTER TABLE calidad.plan_mejora ADD CONSTRAINT cal_plan_origen_ck CHECK (id_indicador IS NOT NULL OR id_estandar IS NOT NULL)');
        DB::statement('ALTER TABLE calidad.plan_mejora ADD CONSTRAINT cal_plan_fechas_ck CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)');
        DB::statement('ALTER TABLE calidad.accion_mejora ADD CONSTRAINT cal_accion_avance_ck CHECK (avance_porcentaje BETWEEN 0 AND 100)');
        DB::statement('ALTER TABLE calidad.comite_calidad_miembro ADD CONSTRAINT cal_comite_fechas_ck CHECK (fecha_fin IS NULL OR fecha_fin >= fecha_inicio)');
    }
};
