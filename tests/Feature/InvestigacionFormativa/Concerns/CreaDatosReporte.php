<?php

namespace Tests\Feature\InvestigacionFormativa\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Datos de lectura para los reportes de sección.
 *
 * Requiere también el trait CreaDatosInvestigacion, que crea la sección,
 * los grupos, la configuración y los usuarios base.
 */
trait CreaDatosReporte
{
    protected array $datos;

    /**
     * Proyecto completo de infraestructura en la sección 9901.
     */
    protected function proyecto(): int
    {
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->configurarSeccionCompleta(9901);
        $rubrica = DB::table('investigacion_formativa.seccion_configuracion')->where('id_seccion', 9901)->value('id_rubrica');
        foreach ([2 => 4, 3 => 2] as $orden => $maximo) {
            $idCriterio = DB::table('investigacion_formativa.criterio')->insertGetId([
                'id_rubrica' => $rubrica, 'descripcion' => 'Criterio '.$orden, 'orden' => $orden, 'puntaje_maximo' => $maximo,
            ], 'id_criterio');

            foreach ([1, 2, 3, 4] as $nivel) {
                DB::table('investigacion_formativa.criterio_nivel')->insert([
                    'id_criterio' => $idCriterio,
                    'nivel' => $nivel,
                    'descripcion' => 'Nivel '.$nivel,
                    'puntaje' => min($nivel, $maximo),
                ]);
            }
        }

        return $this->otroProyecto('Primero', 9951);
    }

    protected function otroProyecto(string $nombre, ?int $grupo = null, int $idLinea = 9801): int
    {
        $grupo ??= DB::table('investigacion_formativa.grupo')->insertGetId(['id_seccion' => 9901, 'nombre' => $nombre], 'id_grupo');

        return DB::table('investigacion_formativa.proyecto')->insertGetId([
            'id_grupo' => $grupo, 'id_linea' => $idLinea, 'id_tipo_producto' => 9981,
            'titulo' => $nombre, 'estado' => 'Aceptado', 'version' => 1,
        ], 'id_proyecto');
    }

    protected function evaluar(int $proyecto, int $unidad, array $puntos): void
    {
        // Fixture de lectura: no usa el gateway de escritura de producción.
        $archivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => DB::table('seguridad.modulo_sistema')->where('codigo', 'investigacion_formativa')->value('id_modulo'),
            'nombre_original' => 'fixture.pdf', 'ruta_almacenamiento' => 'investigacion-formativa/test/fixture-'.$proyecto.'-'.$unidad.'.pdf',
            'tipo_mime' => 'application/pdf', 'tamano_bytes' => 10, 'hash_sha256' => str_repeat('b', 64), 'subido_por' => 8802,
        ], 'id_archivo');
        $entregable = DB::table('investigacion_formativa.entregable')->where('id_seccion', 9901)->where('orden', $unidad)->first();
        $entrega = DB::table('investigacion_formativa.entrega')->insertGetId([
            'id_proyecto' => $proyecto, 'id_entregable' => $entregable->id_entregable, 'version' => 1, 'id_archivo' => $archivo,
        ], 'id_entrega');
        $evaluacion = DB::table('investigacion_formativa.evaluacion')->insertGetId([
            'id_entrega' => $entrega, 'id_docente_evaluador' => 9801, 'version' => 1, 'sin_observaciones' => true,
        ], 'id_evaluacion');
        foreach (DB::table('investigacion_formativa.criterio')->where('id_rubrica', $entregable->id_rubrica)->orderBy('orden')->get() as $indice => $criterio) {
            DB::table('investigacion_formativa.evaluacion_criterio')->insert([
                'id_evaluacion' => $evaluacion, 'id_criterio' => $criterio->id_criterio, 'puntaje' => $puntos[$indice],
            ]);
        }
    }

    protected function encuestaConRespuestas(): void
    {
        DB::table('investigacion_formativa.participante_seccion')->where('id_seccion', 9901)->update(['estado' => 'activo']);
        $encuesta = DB::table('investigacion_formativa.encuesta')->insertGetId([
            'id_seccion' => 9901, 'estado' => 'cerrada', 'creada_por' => $this->datos['users']['director']->id,
            'publicada_en' => now('UTC'), 'cerrada_en' => now('UTC'), 'version' => 3,
        ], 'id_encuesta');
        $preguntas = [];
        foreach ([1, 2] as $orden) {
            $preguntas[] = DB::table('investigacion_formativa.encuesta_pregunta')->insertGetId([
                'id_encuesta' => $encuesta, 'orden' => $orden, 'texto' => 'Pregunta '.$orden,
            ], 'id_pregunta');
        }
        foreach ([9901, 9902, 9905] as $indice => $estudiante) {
            $respuesta = DB::table('investigacion_formativa.encuesta_respuesta')->insertGetId([
                'id_encuesta' => $encuesta, 'id_estudiante' => $estudiante,
                'comentario' => ['<script>primero</script>', 'Comentario dos', 'Comentario tres'][$indice],
                'respondida_en' => '2026-11-01 12:00:0'.$indice.'+00',
            ], 'id_respuesta');
            foreach ($preguntas as $posicion => $pregunta) {
                DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                    'id_encuesta' => $encuesta, 'id_respuesta' => $respuesta, 'id_pregunta' => $pregunta,
                    'valor' => $posicion === 0 ? [1, 3, 5][$indice] : 5,
                ]);
            }
        }
    }
}
