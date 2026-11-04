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

        Schema::create('gestion_matriculas.cronograma_matricula', function (Blueprint $table) {
            $table->bigIncrements('id_cronograma');
            $table->bigInteger('id_periodo');
            $table->string('tipo', 30);
            $table->timestampTz('fecha_inicio');
            $table->timestampTz('fecha_fin');
            $table->unique(['id_periodo', 'tipo'], 'mat_crono_periodo_tipo_uq');
            $table->foreign('id_periodo', 'mat_crono_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.limite_creditos', function (Blueprint $table) {
            $table->bigIncrements('id_limite');
            $table->bigInteger('id_estudiante');
            $table->bigInteger('id_periodo');
            $table->decimal('creditos_maximos', 5, 2);
            $table->text('motivo')->nullable();
            $table->unique(['id_estudiante', 'id_periodo'], 'mat_lim_est_periodo_uq');
            $table->index('id_periodo', 'mat_lim_periodo_idx');
            $table->foreign('id_estudiante', 'mat_lim_estudiante_fk')
                ->references('id_estudiante')->on('core.estudiante')->restrictOnDelete();
            $table->foreign('id_periodo', 'mat_lim_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.matricula', function (Blueprint $table) {
            $table->bigIncrements('id_matricula');
            $table->bigInteger('id_estudiante');
            $table->bigInteger('id_periodo');
            $table->bigInteger('id_cronograma');
            $table->bigInteger('id_solicitud_prematricula')->nullable();
            $table->string('estado', 30);
            $table->bigInteger('registrado_por');
            $table->timestampTz('registrada_en')->useCurrent();
            $table->unique(['id_estudiante', 'id_periodo'], 'mat_matricula_est_periodo_uq');
            $table->index('id_periodo', 'mat_matricula_periodo_idx');
            $table->index('id_cronograma', 'mat_matricula_crono_idx');
            $table->index('id_solicitud_prematricula', 'mat_matricula_pre_idx');
            $table->index('registrado_por', 'mat_matricula_usuario_idx');
            $table->foreign('id_estudiante', 'mat_matricula_estudiante_fk')
                ->references('id_estudiante')->on('core.estudiante')->restrictOnDelete();
            $table->foreign('id_periodo', 'mat_matricula_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
            $table->foreign('id_cronograma', 'mat_matricula_crono_fk')
                ->references('id_cronograma')->on('gestion_matriculas.cronograma_matricula')->restrictOnDelete();
            $table->foreign('registrado_por', 'mat_matricula_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.matricula_detalle', function (Blueprint $table) {
            $table->bigIncrements('id_detalle');
            $table->bigInteger('id_matricula');
            $table->bigInteger('id_seccion');
            $table->string('estado', 30);
            $table->decimal('nota_final', 5, 2)->nullable();
            $table->timestampTz('retirado_en')->nullable();
            $table->unique(['id_matricula', 'id_seccion'], 'mat_det_matricula_seccion_uq');
            $table->index('id_seccion', 'mat_det_seccion_idx');
            $table->foreign('id_matricula', 'mat_det_matricula_fk')
                ->references('id_matricula')->on('gestion_matriculas.matricula')->restrictOnDelete();
            $table->foreign('id_seccion', 'mat_det_seccion_fk')
                ->references('id_seccion')->on('core.seccion')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.componente_evaluacion', function (Blueprint $table) {
            $table->bigIncrements('id_componente');
            $table->bigInteger('id_seccion');
            $table->string('nombre', 100);
            $table->decimal('peso', 5, 2);
            $table->smallInteger('orden');
            $table->unique(['id_seccion', 'nombre'], 'mat_comp_seccion_nombre_uq');
            $table->foreign('id_seccion', 'mat_comp_seccion_fk')
                ->references('id_seccion')->on('core.seccion')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.nota', function (Blueprint $table) {
            $table->bigInteger('id_detalle');
            $table->bigInteger('id_componente');
            $table->decimal('valor', 5, 2);
            $table->bigInteger('registrado_por');
            $table->timestampTz('registrado_en')->useCurrent();
            $table->primary(['id_detalle', 'id_componente'], 'mat_nota_pk');
            $table->index('id_componente', 'mat_nota_componente_idx');
            $table->index('registrado_por', 'mat_nota_usuario_idx');
            $table->foreign('id_detalle', 'mat_nota_detalle_fk')
                ->references('id_detalle')->on('gestion_matriculas.matricula_detalle')->restrictOnDelete();
            $table->foreign('id_componente', 'mat_nota_componente_fk')
                ->references('id_componente')->on('gestion_matriculas.componente_evaluacion')->restrictOnDelete();
            $table->foreign('registrado_por', 'mat_nota_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.asistencia', function (Blueprint $table) {
            $table->bigInteger('id_detalle');
            $table->date('fecha');
            $table->string('estado', 30);
            $table->primary(['id_detalle', 'fecha'], 'mat_asistencia_pk');
            $table->foreign('id_detalle', 'mat_asistencia_detalle_fk')
                ->references('id_detalle')->on('gestion_matriculas.matricula_detalle')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.acta_notas', function (Blueprint $table) {
            $table->bigIncrements('id_acta');
            $table->bigInteger('id_seccion')->unique('mat_acta_seccion_uq');
            $table->string('estado', 30);
            $table->bigInteger('cerrada_por')->nullable();
            $table->timestampTz('cerrada_en')->nullable();
            $table->index('cerrada_por', 'mat_acta_usuario_idx');
            $table->foreign('id_seccion', 'mat_acta_seccion_fk')
                ->references('id_seccion')->on('core.seccion')->restrictOnDelete();
            $table->foreign('cerrada_por', 'mat_acta_usuario_fk')
                ->references('id_usuario')->on('seguridad.usuario')->restrictOnDelete();
        });

        Schema::create('gestion_matriculas.reserva_matricula', function (Blueprint $table) {
            $table->bigIncrements('id_reserva');
            $table->bigInteger('id_estudiante');
            $table->bigInteger('id_periodo');
            $table->text('motivo');
            $table->string('resolucion', 100)->nullable();
            $table->timestampTz('registrada_en')->useCurrent();
            $table->unique(['id_estudiante', 'id_periodo'], 'mat_reserva_est_periodo_uq');
            $table->index('id_periodo', 'mat_reserva_periodo_idx');
            $table->foreign('id_estudiante', 'mat_reserva_estudiante_fk')
                ->references('id_estudiante')->on('core.estudiante')->restrictOnDelete();
            $table->foreign('id_periodo', 'mat_reserva_periodo_fk')
                ->references('id_periodo')->on('core.periodo_academico')->restrictOnDelete();
        });

        $this->addChecks();
        $this->createHistoryView();
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP VIEW IF EXISTS gestion_matriculas.v_historial_academico');
        Schema::dropIfExists('gestion_matriculas.reserva_matricula');
        Schema::dropIfExists('gestion_matriculas.acta_notas');
        Schema::dropIfExists('gestion_matriculas.asistencia');
        Schema::dropIfExists('gestion_matriculas.nota');
        Schema::dropIfExists('gestion_matriculas.componente_evaluacion');
        Schema::dropIfExists('gestion_matriculas.matricula_detalle');
        Schema::dropIfExists('gestion_matriculas.matricula');
        Schema::dropIfExists('gestion_matriculas.limite_creditos');
        Schema::dropIfExists('gestion_matriculas.cronograma_matricula');
    }

    private function addChecks(): void
    {
        DB::statement(<<<'SQL'
            ALTER TABLE gestion_matriculas.cronograma_matricula
            ADD CONSTRAINT mat_crono_fechas_ck CHECK (fecha_fin > fecha_inicio)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE gestion_matriculas.limite_creditos
            ADD CONSTRAINT mat_lim_creditos_ck CHECK (creditos_maximos >= 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE gestion_matriculas.matricula_detalle
            ADD CONSTRAINT mat_det_nota_ck CHECK (nota_final IS NULL OR nota_final BETWEEN 0 AND 20)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE gestion_matriculas.componente_evaluacion
            ADD CONSTRAINT mat_comp_peso_orden_ck CHECK (peso > 0 AND peso <= 100 AND orden > 0)
            SQL);
        DB::statement(<<<'SQL'
            ALTER TABLE gestion_matriculas.nota
            ADD CONSTRAINT mat_nota_valor_ck CHECK (valor BETWEEN 0 AND 20)
            SQL);
    }

    private function createHistoryView(): void
    {
        DB::statement(<<<'SQL'
            CREATE VIEW gestion_matriculas.v_historial_academico AS
            SELECT m.id_estudiante,
                   s.id_periodo,
                   cp.id_curso,
                   cp.id_curso_plan,
                   c.codigo AS codigo_curso,
                   c.creditos,
                   d.nota_final,
                   d.estado,
                   CASE
                       WHEN d.estado <> 'Matriculado' THEN d.estado
                       WHEN d.nota_final IS NULL THEN 'En curso'
                       WHEN d.nota_final >= 10.5 THEN 'Aprobado'
                       ELSE 'Desaprobado'
                   END AS condicion
            FROM gestion_matriculas.matricula m
            JOIN gestion_matriculas.matricula_detalle d ON d.id_matricula = m.id_matricula
            JOIN core.seccion s ON s.id_seccion = d.id_seccion
            JOIN core.curso_plan cp ON cp.id_curso_plan = s.id_curso_plan
            JOIN core.curso c ON c.id_curso = cp.id_curso
            WHERE m.estado <> 'Anulada'
            SQL);
    }
};
