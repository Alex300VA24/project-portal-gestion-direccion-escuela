<?php

namespace Tests\Feature\InvestigacionFormativa;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class EvaluacionesTest extends TestCase
{
    use CreaDatosInvestigacion;
    use DatabaseTransactions;

    private array $datos;

    protected function setUp(): void
    {
        parent::setUp();

        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }

        $this->datos = $this->crearDatosInvestigacion();
        $this->crearGruposInvestigacion();
        $this->crearTipoProducto();
        $this->crearEscenarioDeEvaluacion();
    }

    public function test_deriva_los_puntajes_y_rechaza_datos_cliente_manipulados(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload([
                'id_docente_evaluador' => 9802,
                'puntaje_total' => 20,
                'nota' => 20,
            ]))
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasNoErrors();

        $evaluacion = DB::table('investigacion_formativa.evaluacion')
            ->where('id_entrega', 9981)
            ->first();

        $this->assertNotNull($evaluacion);
        $this->assertSame(1, (int) $evaluacion->version);
        $this->assertSame(9801, (int) $evaluacion->id_docente_evaluador);
        $this->assertSame('Evaluación con puntajes derivados.', $evaluacion->comentario);
        $this->assertFalse((bool) $evaluacion->sin_observaciones);

        $criterios = DB::table('investigacion_formativa.evaluacion_criterio')
            ->where('id_evaluacion', $evaluacion->id_evaluacion)
            ->orderBy('id_criterio')
            ->get()
            ->keyBy('id_criterio');

        $this->assertCount(3, $criterios);
        $this->assertSame(3, (int) $criterios[9963]->nivel);
        $this->assertSame('3.00', (string) $criterios[9963]->puntaje);
        $this->assertSame(2, (int) $criterios[9964]->nivel);
        $this->assertSame('2.00', (string) $criterios[9964]->puntaje);
        $this->assertSame(1, (int) $criterios[9965]->nivel);
        $this->assertSame('1.00', (string) $criterios[9965]->puntaje);

        $observacion = DB::table('investigacion_formativa.observacion')
            ->where('id_evaluacion', $evaluacion->id_evaluacion)
            ->first();

        $this->assertNotNull($observacion);
        $this->assertSame(1, (int) $observacion->version_evaluacion);
        $this->assertSame('Ampliar la justificación del problema.', $observacion->descripcion);
        $this->assertSame(8802, (int) $observacion->registrado_por);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get($this->rutaGrupo())
            ->assertOk()
            ->assertSee('12.00')
            ->assertSee('Ampliar la justificación del problema.');
    }

    public function test_las_migraciones_instalan_y_aplican_las_restricciones_de_evaluacion(): void
    {
        $restricciones = DB::select(<<<'SQL'
            SELECT conname
            FROM pg_constraint
            WHERE conname IN (
                'if_evaluacion_version_ck',
                'if_evaluacion_criterio_nivel_ck',
                'if_evaluacion_criterio_nivel_fk',
                'if_observacion_evaluacion_fk',
                'if_observacion_version_evaluacion_ck'
            )
            SQL);

        $this->assertCount(5, $restricciones);

        try {
            DB::transaction(function (): void {
                DB::table('investigacion_formativa.evaluacion')->insert([
                    'id_entrega' => 9981,
                    'id_docente_evaluador' => 9801,
                    'version' => 0,
                ]);
            });

            $this->fail('La versión cero de evaluación debió ser rechazada.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);
        }

        $idEvaluacion = DB::table('investigacion_formativa.evaluacion')->insertGetId([
            'id_entrega' => 9981,
            'id_docente_evaluador' => 9801,
            'version' => 1,
        ], 'id_evaluacion');

        try {
            DB::transaction(function () use ($idEvaluacion): void {
                DB::table('investigacion_formativa.evaluacion_criterio')->insert([
                    'id_evaluacion' => $idEvaluacion,
                    'id_criterio' => 9963,
                    'nivel' => 5,
                    'puntaje' => 1,
                ]);
            });

            $this->fail('El nivel de evaluación inexistente debió ser rechazado.');
        } catch (QueryException $exception) {
            $this->assertContains($exception->errorInfo[0] ?? null, ['23503', '23514']);
        }

        try {
            DB::transaction(function () use ($idEvaluacion): void {
                DB::table('investigacion_formativa.observacion')->insert([
                    'id_entrega' => 9981,
                    'id_evaluacion' => $idEvaluacion,
                    'version_evaluacion' => 0,
                    'descripcion' => 'Revisión inválida.',
                    'registrado_por' => 8802,
                ]);
            });

            $this->fail('La versión de observación cero debió ser rechazada.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);
        }
    }

    public function test_una_correccion_incrementa_la_version_y_conserva_el_historial(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertRedirect($this->rutaGrupo());

        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload([
                'criterios' => [
                    ['id_criterio' => 9963, 'nivel' => 4],
                    ['id_criterio' => 9964, 'nivel' => 2],
                    ['id_criterio' => 9965, 'nivel' => 1],
                ],
                'comentario' => 'Corrección de la evaluación.',
                'observaciones' => ['La conclusion ya responde al objetivo.'],
                'version' => 1,
            ]))
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasNoErrors();

        $evaluacion = DB::table('investigacion_formativa.evaluacion')
            ->where('id_entrega', 9981)
            ->first();

        $this->assertSame(2, (int) $evaluacion->version);
        $this->assertSame('Corrección de la evaluación.', $evaluacion->comentario);
        $this->assertSame(
            '4.00',
            (string) DB::table('investigacion_formativa.evaluacion_criterio')
                ->where('id_evaluacion', $evaluacion->id_evaluacion)
                ->where('id_criterio', 9963)
                ->value('puntaje'),
        );

        $observaciones = DB::table('investigacion_formativa.observacion')
            ->where('id_evaluacion', $evaluacion->id_evaluacion)
            ->orderBy('version_evaluacion')
            ->get();

        $this->assertCount(2, $observaciones);
        $this->assertSame(1, (int) $observaciones[0]->version_evaluacion);
        $this->assertSame('Ampliar la justificación del problema.', $observaciones[0]->descripcion);
        $this->assertSame(2, (int) $observaciones[1]->version_evaluacion);
        $this->assertSame('La conclusion ya responde al objetivo.', $observaciones[1]->descripcion);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get($this->rutaGrupo())
            ->assertOk()
            ->assertSee('14.00')
            ->assertSee('Historial de observaciones')
            ->assertSee('Ampliar la justificación del problema.')
            ->assertSee('La conclusion ya responde al objetivo.');
    }

    public function test_una_version_desactualizada_devuelve_conflicto_sin_sobrescribir_la_evaluacion(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertRedirect($this->rutaGrupo());

        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload([
                'comentario' => 'No debe guardarse.',
                'version' => 0,
            ]))
            ->assertStatus(409)
            ->assertSee('La evaluación cambió en otra sesión.');

        $evaluacion = DB::table('investigacion_formativa.evaluacion')
            ->where('id_entrega', 9981)
            ->first();

        $this->assertSame(1, (int) $evaluacion->version);
        $this->assertSame('Evaluación con puntajes derivados.', $evaluacion->comentario);
    }

    public function test_exige_criterios_exactos_niveles_validos_y_observaciones_explicitas(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload([
                'criterios' => [
                    ['id_criterio' => 9963, 'nivel' => 3],
                    ['id_criterio' => 9963, 'nivel' => 2],
                    ['id_criterio' => 9965, 'nivel' => 1],
                ],
            ]))
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasErrors('criterios.1.id_criterio');

        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload([
                'criterios' => [
                    ['id_criterio' => 9963, 'nivel' => 3],
                    ['id_criterio' => 9964, 'nivel' => 2],
                    ['id_criterio' => 9967, 'nivel' => 1],
                ],
            ]))
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasErrors('evaluacion');

        DB::table('investigacion_formativa.criterio_nivel')
            ->where('id_criterio', 9963)
            ->where('nivel', 3)
            ->delete();

        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasErrors('evaluacion');

        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload([
                'observaciones' => [],
            ]))
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasErrors('observaciones');

        $this->assertSame(0, DB::table('investigacion_formativa.evaluacion')->count());
    }

    public function test_guarda_la_opcion_explicita_sin_observaciones(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload([
                'sin_observaciones' => 1,
                'observaciones' => [],
            ]))
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasNoErrors();

        $evaluacion = DB::table('investigacion_formativa.evaluacion')
            ->where('id_entrega', 9981)
            ->first();

        $this->assertTrue((bool) $evaluacion->sin_observaciones);
        $this->assertSame(0, DB::table('investigacion_formativa.observacion')->count());
    }

    public function test_solo_el_docente_responsable_puede_evaluar_y_la_entrega_debe_pertenecer_al_grupo(): void
    {
        $this->from($this->rutaGrupo())
            ->put($this->rutaEvaluacion(), $this->payload())
            ->assertRedirect('/login');

        $this->actingAs($this->datos['users']['docente_b'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertForbidden();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertForbidden();

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertForbidden();

        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put(route('investigacion-formativa.docente.evaluaciones.update', [9901, 9951, 9982]), $this->payload())
            ->assertNotFound();

        $this->assertSame(0, DB::table('investigacion_formativa.evaluacion')->count());
    }

    public function test_una_seccion_deshabilitada_rechaza_la_evaluacion(): void
    {
        DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->update(['habilitada' => false]);

        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertRedirect($this->rutaGrupo())
            ->assertSessionHasErrors('evaluacion');

        $this->assertSame(0, DB::table('investigacion_formativa.evaluacion')->count());
    }

    public function test_las_evaluaciones_legacy_sin_nivel_ni_estado_de_observaciones_siguen_siendo_legibles(): void
    {
        $idEvaluacion = DB::table('investigacion_formativa.evaluacion')->insertGetId([
            'id_entrega' => 9981,
            'id_docente_evaluador' => 9801,
            'comentario' => 'Evaluación registrada antes de la versión de niveles.',
            'version' => 1,
            'evaluado_en' => now('UTC')->format('Y-m-d H:i:sP'),
        ], 'id_evaluacion');

        DB::table('investigacion_formativa.evaluacion_criterio')->insert([
            'id_evaluacion' => $idEvaluacion,
            'id_criterio' => 9963,
            'nivel' => null,
            'puntaje' => 3,
        ]);

        DB::table('investigacion_formativa.observacion')->insert([
            'id_entrega' => 9981,
            'descripcion' => 'Observación histórica sin revisión asociada.',
            'estado' => 'Pendiente',
            'registrado_por' => 8802,
            'registrado_en' => now('UTC')->format('Y-m-d H:i:sP'),
        ]);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get($this->rutaGrupo())
            ->assertOk()
            ->assertSee('Evaluación registrada antes de la versión de niveles.')
            ->assertSee('Observación histórica sin revisión asociada.')
            ->assertSee('revisión histórica');
    }

    public function test_la_version_anterior_conserva_su_nota_y_la_mas_reciente_queda_pendiente(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->from($this->rutaGrupo())->put($this->rutaEvaluacion(), $this->payload())
            ->assertRedirect($this->rutaGrupo());

        $idArchivo = $this->crearArchivo('avance-version-2.pdf');
        DB::table('investigacion_formativa.entrega')->insert([
            'id_entrega' => 9983,
            'id_proyecto' => 9961,
            'id_entregable' => 9971,
            'version' => 2,
            'id_archivo' => $idArchivo,
        ]);

        $this->actingAs($this->datos['users']['docente_a'])
            ->get($this->rutaGrupo())
            ->assertOk()
            ->assertSee('12.00')
            ->assertSee('Pendiente de evaluación')
            ->assertSee('Versión más reciente');

        $this->assertSame(1, DB::table('investigacion_formativa.evaluacion')->count());
    }

    /**
     * @param  array<string, mixed>  $sobrescrituras
     * @return array<string, mixed>
     */
    private function payload(array $sobrescrituras = []): array
    {
        return array_replace([
            'criterios' => [
                ['id_criterio' => 9963, 'nivel' => 3],
                ['id_criterio' => 9964, 'nivel' => 2],
                ['id_criterio' => 9965, 'nivel' => 1],
            ],
            'comentario' => 'Evaluación con puntajes derivados.',
            'sin_observaciones' => 0,
            'observaciones' => ['Ampliar la justificación del problema.'],
            'version' => 0,
        ], $sobrescrituras);
    }

    private function rutaEvaluacion(): string
    {
        return route('investigacion-formativa.docente.evaluaciones.update', [9901, 9951, 9981]);
    }

    private function rutaGrupo(): string
    {
        return route('investigacion-formativa.docente.grupos.show', [9901, 9951]);
    }

    private function crearEscenarioDeEvaluacion(): void
    {
        DB::table('investigacion_formativa.competencia_seccion')->insert([
            'id_competencia' => 9861,
            'id_seccion' => 9901,
            'codigo' => 'C1',
            'descripcion' => 'Diseña el problema de investigación.',
            'fuente' => 'Sílabo aprobado 2026',
            'seleccionada' => true,
        ]);

        DB::table('investigacion_formativa.rubrica')->insert([
            'id_rubrica' => 9962,
            'nombre' => 'Rúbrica de evaluación',
            'descripcion' => 'Criterios de la prueba de evaluación.',
            'id_docente_autor' => 9801,
        ]);

        $criterios = [
            9963 => [4, 'Planteamiento del problema'],
            9964 => [4, 'Marco teórico'],
            9965 => [2, 'Conclusiones'],
        ];

        foreach ($criterios as $idCriterio => [$maximo, $descripcion]) {
            DB::table('investigacion_formativa.criterio')->insert([
                'id_criterio' => $idCriterio,
                'id_rubrica' => 9962,
                'descripcion' => $descripcion,
                'puntaje_maximo' => $maximo,
                'orden' => array_search($idCriterio, array_keys($criterios), true) + 1,
            ]);

            $puntajes = $idCriterio === 9965 ? [1, 2, 2, 2] : [1, 2, 3, 4];

            foreach ([1, 2, 3, 4] as $nivel) {
                DB::table('investigacion_formativa.criterio_nivel')->insert([
                    'id_criterio' => $idCriterio,
                    'nivel' => $nivel,
                    'descripcion' => "Nivel {$nivel}",
                    'puntaje' => $puntajes[$nivel - 1],
                ]);
            }
        }

        DB::table('investigacion_formativa.rubrica')->insert([
            'id_rubrica' => 9966,
            'nombre' => 'Rúbrica ajena',
            'id_docente_autor' => 9802,
        ]);
        DB::table('investigacion_formativa.criterio')->insert([
            'id_criterio' => 9967,
            'id_rubrica' => 9966,
            'descripcion' => 'Criterio de otra rúbrica',
            'puntaje_maximo' => 2,
            'orden' => 1,
        ]);

        foreach ([1, 2, 3] as $indice => $unidad) {
            DB::table('investigacion_formativa.entregable')->insert([
                'id_entregable' => 9971 + $indice,
                'id_seccion' => 9901,
                'nombre' => 'Unidad '.$unidad,
                'fecha_limite' => now('UTC')->addDays(30 * $unidad)->format('Y-m-d H:i:sP'),
                'orden' => $unidad,
                'id_rubrica' => 9962,
            ]);
        }

        DB::table('investigacion_formativa.proyecto')->insert([
            'id_proyecto' => 9961,
            'id_grupo' => 9951,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => 'Proyecto evaluable',
            'estado' => 'Aceptado',
            'version' => 1,
        ]);

        DB::table('investigacion_formativa.proyecto')->insert([
            'id_proyecto' => 9962,
            'id_grupo' => 9954,
            'id_linea' => 9801,
            'id_tipo_producto' => 9981,
            'titulo' => 'Proyecto de otro grupo',
            'estado' => 'Aceptado',
            'version' => 1,
        ]);

        DB::table('investigacion_formativa.seccion_configuracion')->insert([
            'id_seccion' => 9901,
            'habilitada' => true,
            'id_rubrica' => 9962,
            'configurado_en' => now('UTC')->format('Y-m-d H:i:sP'),
            'version' => 1,
        ]);

        DB::table('investigacion_formativa.entrega')->insert([
            'id_entrega' => 9981,
            'id_proyecto' => 9961,
            'id_entregable' => 9971,
            'version' => 1,
            'id_archivo' => $this->crearArchivo('avance-version-1.pdf'),
        ]);

        DB::table('investigacion_formativa.entrega')->insert([
            'id_entrega' => 9982,
            'id_proyecto' => 9962,
            'id_entregable' => 9971,
            'version' => 1,
            'id_archivo' => $this->crearArchivo('avance-otro-grupo.pdf'),
        ]);
    }

    private function crearArchivo(string $nombre): int
    {
        $idModulo = DB::table('seguridad.modulo_sistema')
            ->where('codigo', 'investigacion_formativa')
            ->value('id_modulo');

        return DB::table('comun.archivo')->insertGetId([
            'id_modulo' => $idModulo,
            'nombre_original' => $nombre,
            'ruta_almacenamiento' => "investigacion-formativa/test/{$nombre}",
            'tipo_mime' => 'application/pdf',
            'tamano_bytes' => 10,
            'hash_sha256' => hash('sha256', $nombre),
            'subido_por' => 8802,
        ], 'id_archivo');
    }
}
