<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lleva el esquema seguimiento_egresado a la versión final del modelo de datos del módulo (45 tablas).
 *
 * La migración 2026_11_04_040000 creó una primera versión (40 tablas, esquemas_y_origen_de_tablas.pdf) que no
 * incluye lo que el módulo necesita: propósito, estado y vencimiento de las invitaciones, contacto versionado,
 * campos de la medición laboral, evaluaciones por cohorte y periodo, datos del padrón y las vistas de indicadores.
 * Esa versión no tiene datos ni otro módulo depende de ella (no hay FK entre esquemas de módulos), por lo que se
 * reemplaza por completo. La migración anterior no se edita: down() la vuelve a crear.
 *
 * El modelo está en Database/Sql/01_modelo_final.sql (idéntico al diseño físico aprobado, sin BEGIN/COMMIT porque
 * Laravel abre la transacción). Sus tablas se crean sin calificar y llegan al esquema por el search_path local.
 */
return new class extends Migration
{
    /** Tablas de la primera versión. CASCADE hace innecesario el orden, que aun así va de las hojas a la raíz. */
    private const VERSION_ANTERIOR = [
        'plan_accion_evidencia', 'plan_accion', 'mejora_curricular', 'reporte_indicador', 'reporte_generado',
        'indicador_egresado', 'respuesta_empresa_habilidad', 'respuesta_empresa', 'satisfaccion_egresado',
        'evaluacion_oe', 'evaluacion_competencia', 'medicion_habilidad', 'medicion_laboral', 'invitacion_empresa',
        'invitacion_egresado', 'campana_canal', 'campana_cohorte', 'campana_seguimiento', 'importacion_fila',
        'importacion_padron', 'formacion_posterior', 'contacto_egresado', 'historial_estado_academico',
        'grado_egresado', 'egresado', 'objetivo_educacional', 'competencia', 'cohorte',
        'cat_canal_envio', 'cat_nivel_satisfaccion', 'cat_nivel_logro', 'cat_situacion_laboral',
        'cat_modalidad_trabajo', 'cat_modalidad_laboral', 'cat_tipo_formacion', 'cat_estado_formacion',
        'cat_estado_academico', 'cat_habilidad', 'cat_categoria_habilidad', 'cat_area_profesional',
    ];

    /** Tablas del modelo final, de las hojas a la raíz (para down()). */
    private const MODELO_FINAL = [
        'satisfaccion_egresado', 'respuesta_empresa_habilidad', 'medicion_habilidad', 'formacion_posterior',
        'respuesta_empresa', 'medicion_laboral', 'reporte_indicador', 'plan_accion', 'mejora_curricular',
        'invitacion_empresa', 'invitacion_egresado', 'importacion_fila', 'historial_estado_academico',
        'evaluacion_oe', 'evaluacion_competencia', 'contacto_egresado', 'campania_canal', 'campania_cohorte',
        'reporte_generado', 'importacion_padron', 'egresado', 'campania_seguimiento', 'auditoria',
        'archivo_evidencia', 'usuario_interno', 'objetivo_educacional', 'competencia', 'cohorte',
        'cat_habilidad', 'rol_interno', 'plan_curricular', 'cat_sector_empresa', 'cat_canal_envio',
        'cat_tipo_formacion', 'cat_situacion_laboral', 'cat_sector_laboral', 'cat_pais', 'cat_nivel_satisfaccion',
        'cat_nivel_logro', 'cat_modalidad_trabajo', 'cat_modalidad_laboral', 'cat_estado_formacion',
        'cat_estado_academico', 'cat_categoria_habilidad', 'cat_area_profesional',
    ];

    private const FUNCIONES = [
        'fn_validar_plan_evaluacion', 'fn_bloquear_cambio_plan', 'fn_validar_respuesta_empresa', 'fn_validar_medicion',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::VERSION_ANTERIOR as $tabla) {
            DB::statement("DROP TABLE IF EXISTS seguimiento_egresado.{$tabla} CASCADE");
        }

        DB::statement('SET LOCAL search_path TO seguimiento_egresado, public');
        DB::unprepared(file_get_contents(dirname(__DIR__).'/Sql/01_modelo_final.sql'));

        // Los cuerpos de los disparadores nombran tablas sin calificar y se resuelven al ejecutarse, no al crearse:
        // cada función lleva su propio search_path para no depender del de la conexión.
        foreach (self::FUNCIONES as $funcion) {
            DB::statement("ALTER FUNCTION seguimiento_egresado.{$funcion}() SET search_path = seguimiento_egresado, public");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (self::MODELO_FINAL as $tabla) {
            DB::statement("DROP TABLE IF EXISTS seguimiento_egresado.{$tabla} CASCADE");
        }

        foreach (self::FUNCIONES as $funcion) {
            DB::statement("DROP FUNCTION IF EXISTS seguimiento_egresado.{$funcion}() CASCADE");
        }

        (require __DIR__.'/2026_11_04_040000_create_seguimiento_egresado_tables.php')->up();
    }
};
