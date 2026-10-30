<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // --------------------------------------------------------------------
        // 1. TABLAS COMPLEMENTARIAS DEL PERFIL DEL ESTUDIANTE
        // --------------------------------------------------------------------
        Schema::create('perfil_socioeconomico', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->unique()->constrained('estudiantes')->onDelete('cascade'); // Relación 1:1 con estudiantes
            $table->string('clasificacion_sisfoh')->nullable();
            $table->boolean('situacion_orfandad')->default(false);
            $table->boolean('es_sustento_familiar')->default(false);
            $table->boolean('violencia_familiar')->default(false);
            $table->integer('nro_dependientes')->default(0);
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_actualizacion')->nullable();
            $table->timestamps();
        });

        Schema::create('tipo_condicion', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('categoria')->nullable(); // Ej: Discapacidad, TEA, Deportista Calificado
            $table->text('base_legal')->nullable();
            $table->timestamps();
        });

        Schema::create('estudiante_condicion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->foreignId('tipo_condicion_id')->constrained('tipo_condicion')->onDelete('cascade');
            $table->string('grado')->nullable();
            $table->string('certificado_conadis')->nullable();
            $table->boolean('requiere_aula_accesible')->default(false);
            $table->boolean('pauta_pedagogica')->default(false);
            $table->boolean('visible_docente')->default(false);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamps();
        });

        Schema::create('deporte', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->timestamps();
        });

        Schema::create('estudiante_deporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade');
            $table->foreignId('deporte_id')->constrained('deporte')->onDelete('cascade');
            $table->string('nivel')->nullable();
            $table->boolean('disponible_seleccion')->default(false);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // 2. MÓDULO DE CASOS DE BIENESTAR Y SEGUIMIENTO
        // --------------------------------------------------------------------
        Schema::create('tipo_caso', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        Schema::create('caso_bienestar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade'); // Alumno afectado
            $table->foreignId('registrado_por_id')->constrained('personas')->onDelete('cascade'); // Personal administrativo / bienestar que registra (tabla general personas)
            $table->foreignId('tipo_caso_id')->constrained('tipo_caso')->onDelete('restrict');
            $table->string('prioridad')->default('MEDIA'); // BAJA, MEDIA, ALTA, CRITICA
            $table->text('descripcion');
            $table->string('estado')->default('ABIERTO'); // ABIIERTO, EN_PROCESO, DERIVADO, CERRADO
            $table->boolean('confidencial')->default(true);
            $table->timestamp('fecha_registro')->useCurrent();
            $table->timestamp('fecha_cierre')->nullable();
            $table->timestamps();
        });

        Schema::create('historial_estado_caso', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_bienestar_id')->constrained('caso_bienestar')->onDelete('cascade');
            $table->foreignId('realizado_por_id')->constrained('personas')->onDelete('cascade'); // Persona que efectúa el cambio de estado
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->text('comentario')->nullable();
            $table->timestamp('fecha_cambio')->useCurrent();
            $table->timestamps();
        });

        Schema::create('derivacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caso_bienestar_id')->constrained('caso_bienestar')->onDelete('cascade');
            $table->foreignId('derivado_por_id')->constrained('personas')->onDelete('cascade'); // Persona general que realiza la derivación
            $table->foreignId('especialista_id')->constrained('profesores')->onDelete('cascade'); // Especialista que atiende (apunta a profesores, ej. psicólogos/tutores)
            $table->text('motivo');
            $table->string('estado')->default('PENDIENTE'); // PENDIENTE, EN_CURSO, ATENDIDO
            $table->timestamp('fecha_derivacion')->useCurrent();
            $table->timestamps();
        });

        Schema::create('seguimiento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('derivacion_id')->constrained('derivacion')->onDelete('cascade');
            $table->timestamp('fecha_sesion')->useCurrent();
            $table->text('nota_avance');
            $table->text('acuerdos')->nullable();
            $table->date('proxima_fecha')->nullable();
            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // 3. MÓDULO DE SOLICITUDES DE APOYO SOCIOECONÓMICO
        // --------------------------------------------------------------------
        Schema::create('solicitud_apoyo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade'); // Estudiante solicitante
            $table->foreignId('revisado_por_id')->nullable()->constrained('personas')->onDelete('set null'); // Personal administrativo que revisa
            $table->foreignId('caso_bienestar_id')->nullable()->constrained('caso_bienestar')->onDelete('set null');
            $table->string('tipo_apoyo'); // Comedor, albergue, beca económica, etc.
            $table->text('motivo');
            $table->string('estado')->default('PENDIENTE'); // PENDIENTE, APROBADO, RECHAZADO
            $table->text('observacion')->nullable();
            $table->timestamp('fecha_envio')->useCurrent();
            $table->timestamp('fecha_resolucion')->nullable();
            $table->timestamps();
        });

        Schema::create('documento_adjunto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_apoyo_id')->constrained('solicitud_apoyo')->onDelete('cascade');
            $table->string('nombre_archivo');
            $table->string('ruta_archivo');
            $table->string('tipo_documento'); // Recibo de luz, boleta de pago, ficha socioeconómica
            $table->integer('tamano_kb');
            $table->timestamp('fecha_subida')->useCurrent();
            $table->timestamps();
        });

        Schema::create('beneficio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_apoyo_id')->unique()->constrained('solicitud_apoyo')->onDelete('cascade');
            $table->string('tipo_beneficio');
            $table->date('fecha_inicio');
            $table->date('fecha_fin')->nullable();
            $table->string('estado')->default('ACTIVO'); // ACTIVO, SUSPENDIDO, FINALIZADO
            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // 4. MÓDULO DE CITAS Y DISPONIBILIDAD
        // --------------------------------------------------------------------
        Schema::create('horario_disponible', function (Blueprint $table) {
            $table->id();
            $table->foreignId('especialista_id')->constrained('profesores')->onDelete('cascade'); // Especialista (profesor/psicólogo)
            $table->date('fecha');
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('estado')->default('DISPONIBLE'); // DISPONIBLE, OCUPADO, BLOQUEADO
            $table->timestamps();
        });

        Schema::create('cita', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estudiante_id')->constrained('estudiantes')->onDelete('cascade'); // Estudiante que reserva
            $table->foreignId('horario_disponible_id')->unique()->constrained('horario_disponible')->onDelete('cascade');
            $table->foreignId('caso_bienestar_id')->nullable()->constrained('caso_bienestar')->onDelete('set null');
            $table->string('tipo_cita'); // Presencial, Virtual, Adaptado TEA
            $table->text('motivo')->nullable();
            $table->string('estado')->default('PROGRAMADA'); // PROGRAMADA, CONFIRMADA, CANCELADA, ATENDIDA
            $table->timestamp('fecha_solicitud')->useCurrent();
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamps();
        });

        // --------------------------------------------------------------------
        // 5. MÓDULO DE NOTIFICACIONES Y REPORTES
        // --------------------------------------------------------------------
        Schema::create('notificacion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('personas')->onDelete('cascade'); // Receptor (apunta a la persona general del ERP)
            $table->foreignId('caso_bienestar_id')->nullable()->constrained('caso_bienestar')->onDelete('cascade');
            $table->string('titulo');
            $table->text('mensaje');
            $table->string('canal')->default('SISTEMA'); // PUSH, CORREO, SISTEMA
            $table->boolean('leida')->default(false);
            $table->timestamp('fecha_envio')->useCurrent();
            $table->timestamps();
        });

        Schema::create('reporte', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generado_por_id')->constrained('personas')->onDelete('cascade'); // Administrador que genera el reporte
            $table->string('tipo_reporte'); // Estadísticas de casos, atenciones psicológicas, beneficios otorgados
            $table->string('formato'); // PDF, EXCEL
            $table->date('periodo_inicio');
            $table->date('periodo_fin');
            $table->timestamp('fecha_generacion')->useCurrent();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reporte');
        Schema::dropIfExists('notificacion');
        Schema::dropIfExists('cita');
        Schema::dropIfExists('horario_disponible');
        Schema::dropIfExists('beneficio');
        Schema::dropIfExists('documento_adjunto');
        Schema::dropIfExists('solicitud_apoyo');
        Schema::dropIfExists('seguimiento');
        Schema::dropIfExists('derivacion');
        Schema::dropIfExists('historial_estado_caso');
        Schema::dropIfExists('caso_bienestar');
        Schema::dropIfExists('tipo_caso');
        Schema::dropIfExists('estudiante_deporte');
        Schema::dropIfExists('deporte');
        Schema::dropIfExists('estudiante_condicion');
        Schema::dropIfExists('tipo_condicion');
        Schema::dropIfExists('perfil_socioeconomico');
    }
};