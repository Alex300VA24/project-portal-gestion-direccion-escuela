<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Expedientes de seguimiento (Tabla maestra del módulo)
        Schema::create('expedientes_seguimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->string('proceso_ingreso'); // Ej: Admisión 2026-I
            $table->string('fuente_ingreso');
            $table->enum('estado_expediente', ['ABIERTO', 'EN_SEGUIMIENTO', 'CERRADO_EGRESO', 'CERRADO_SEPARACION'])->default('ABIERTO');
            $table->timestamps();
        });

        // 2. Fichas F1 - Caracterización (SP1)
        Schema::create('fichas_f1_caracterizacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_seguimiento')->onDelete('cascade');
            $table->enum('estado_f1', ['INCOMPLETO', 'BORRADOR', 'CONFIRMADO'])->default('INCOMPLETO');
            $table->boolean('requiere_nivelacion')->default(false);
            $table->json('datos_faltantes')->nullable();
            $table->timestamps();
        });

        // 3. Programas de nivelación F2 (SP2)
        Schema::create('programas_nivelacion_f2', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->string('nombre_programa');
            $table->text('actividades_previstas');
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->timestamps();
        });

        // 4. Informes de eficacia F3 (SP2)
        Schema::create('informes_eficacia_f3', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programa_nivelacion_id')->constrained('programas_nivelacion_f2')->onDelete('cascade');
            $table->decimal('puntaje_eficacia', 5, 2)->nullable();
            $table->enum('estado_brecha', ['CERRADA', 'PERSISTENTE']);
            $table->text('conclusiones');
            $table->timestamps();
        });

        // 5. Casos de atención (SP4.1)
        Schema::create('casos_atencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expediente_id')->constrained('expedientes_seguimiento')->onDelete('cascade');
            $table->enum('origen_caso', ['ESTUDIANTE', 'ALERTA_SGA', 'CONTINUIDAD', 'COORDINADOR']);
            $table->string('motivo');
            $table->string('experiencia_curricular_id')->nullable(); // Ej: ID de Cálculo I
            $table->enum('estado_caso', ['PENDIENTE', 'PROGRAMADO', 'ATENDIDO', 'CERRADO'])->default('PENDIENTE');
            $table->timestamps();
        });

        // 6. Historial de repitencias de cursos (SP3 - Clave para detectar 2da, 3ra, 4ta vez)
        Schema::create('historial_repitencias_cursos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->string('codigo_curso'); // Ej: MAT101
            $table->integer('numero_intento'); // 1, 2, 3, 4
            $table->string('periodo_academico');
            $table->decimal('nota_final', 4, 2)->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamps();
        });

        // 7. Alertas académicas (SP3)
        Schema::create('alertas_academicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->enum('nivel_riesgo', ['BAJO', 'MEDIO', 'PRIORITARIO']);
            $table->text('descripcion_alerta');
            $table->boolean('revisada_por_coordinador')->default(false);
            $table->timestamps();
        });

        // 8. Asignaciones de tutores (SP4.3)
        Schema::create('asignaciones_tutores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->foreignId('profesor_id')->constrained('profesores')->onDelete('cascade');
            $table->string('periodo_academico');
            $table->timestamps();
        });

        // 9. Citas de atención (SP4.2 y SP4.3)
        Schema::create('citas_atencion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_atencion_id')->constrained('casos_atencion')->onDelete('cascade');
            $table->foreignId('profesor_id')->constrained('profesores')->onDelete('cascade'); // Docente del curso o tutor
            $table->enum('tipo_atencion', ['CONSEJERIA', 'TUTORIA', 'COMBINADA']);
            $table->dateTime('fecha_hora_programada');
            $table->string('ambiente')->nullable();
            $table->enum('estado_cita', ['PROGRAMADA', 'ATENDIDA', 'CANCELADA'])->default('PROGRAMADA');
            $table->timestamps();
        });

        // 10. Fichas F5 - Sesiones (SP5 y SP6)
        Schema::create('fichas_f5_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas_atencion')->onDelete('cascade');
            $table->text('diagnostico_docente');
            $table->text('acuerdos_compromisos');
            $table->enum('resultado_sesion', ['RESUELTA', 'REQUIERE_NUEVA_CONSEJERIA', 'REQUIERE_TUTORIA', 'REQUIERE_ESPECIALIZADA']);
            $table->timestamps();
        });

        // 11. Derivaciones referencias F6-A (SP7)
        Schema::create('derivaciones_referencias_f6a', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_atencion_id')->constrained('casos_atencion')->onDelete('cascade');
            $table->string('area_destino'); // Ej: Psicología, Servicio Social
            $table->text('motivo_derivacion');
            $table->string('canal_envio');
            $table->timestamp('fecha_remision');
            $table->timestamps();
        });

        // 12. Contra-referencias F6-B (SP7)
        Schema::create('contra_referencias_f6b', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referencia_id')->constrained('derivaciones_referencias_f6a')->onDelete('cascade');
            $table->date('fecha_recepcion');
            $table->text('diagnostico_resumen');
            $table->text('recomendaciones_tutor');
            $table->timestamps();
        });

        // 13. Continuidad académica por periodos (SP8 y SP9)
        Schema::create('continuidad_academica_periodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->string('periodo');
            $table->enum('estado_continuidad', ['ACTIVO', 'RESERVA_LICENCIA', 'NO_MATRICULADO_ALERTA', 'PLAN_COMPLETADO', 'SEPARADO_DEFINITIVO']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('continuidad_academica_periodos');
        Schema::dropIfExists('contra_referencias_f6b');
        Schema::dropIfExists('derivaciones_referencias_f6a');
        Schema::dropIfExists('fichas_f5_sesiones');
        Schema::dropIfExists('citas_atencion');
        Schema::dropIfExists('asignaciones_tutores');
        Schema::dropIfExists('alertas_academicas');
        Schema::dropIfExists('historial_repitencias_cursos');
        Schema::dropIfExists('casos_atencion');
        Schema.dropIfExists('informes_eficacia_f3');
        Schema.dropIfExists('programas_nivelacion_f2');
        Schema.dropIfExists('fichas_f1_caracterizacion');
        Schema.dropIfExists('expedientes_seguimiento');
    }
};