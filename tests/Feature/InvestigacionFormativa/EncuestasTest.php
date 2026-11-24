<?php

namespace Tests\Feature\InvestigacionFormativa;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class EncuestasTest extends TestCase
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
        $this->configurarSeccionCompleta(9901);
    }

    public function test_muestra_un_borrador_vacio_antes_del_primer_guardado(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get($this->rutaDirector())
            ->assertOk()
            ->assertSee('No hay preguntas guardadas todavía.')
            ->assertSee('Agregue la primera pregunta para crear el borrador.');

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta')->count());
        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_pregunta')->count());
    }

    public function test_guarda_preguntas_recortadas_conserva_el_orden_e_incrementa_la_version(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => [
                    '  Primera pregunta  ',
                    'Segunda pregunta',
                ],
                'version' => 0,
            ])
            ->assertRedirect($this->rutaDirector())
            ->assertSessionHasNoErrors();

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', 9901)
            ->first();

        $this->assertNotNull($encuesta);
        $this->assertSame('borrador', $encuesta->estado);
        $this->assertSame(1, (int) $encuesta->version);
        $this->assertSame($this->datos['users']['director']->id, (int) $encuesta->creada_por);

        $preguntas = DB::table('investigacion_formativa.encuesta_pregunta')
            ->where('id_encuesta', $encuesta->id_encuesta)
            ->orderBy('orden')
            ->get();

        $this->assertCount(2, $preguntas);
        $this->assertSame(1, (int) $preguntas[0]->orden);
        $this->assertSame('Primera pregunta', $preguntas[0]->texto);
        $this->assertSame(2, (int) $preguntas[1]->orden);
        $this->assertSame('Segunda pregunta', $preguntas[1]->texto);
    }

    public function test_los_limites_de_preguntas_y_texto_se_validan_sin_crear_una_encuesta_incompleta(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => [],
                'version' => 0,
            ])
            ->assertSessionHasErrors('preguntas');

        $preguntasCincuenta = array_map(
            static fn (int $indice): string => "Pregunta {$indice}",
            range(1, 50),
        );

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => $preguntasCincuenta,
                'version' => 0,
            ])
            ->assertRedirect($this->rutaDirector())
            ->assertSessionHasNoErrors();

        $this->assertSame(50, DB::table('investigacion_formativa.encuesta_pregunta')->count());

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => [...$preguntasCincuenta, 'Pregunta adicional'],
                'version' => 1,
            ])
            ->assertSessionHasErrors('preguntas');

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => [str_repeat('a', 1001)],
                'version' => 1,
            ])
            ->assertSessionHasErrors('preguntas.0');

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['   '],
                'version' => 1,
            ])
            ->assertSessionHasErrors('preguntas.0');

        $this->assertSame(50, DB::table('investigacion_formativa.encuesta_pregunta')->count());
        $this->assertSame(
            1,
            (int) DB::table('investigacion_formativa.encuesta')
                ->where('id_seccion', 9901)
                ->value('version'),
        );
    }

    public function test_recupera_el_texto_ingresado_tras_un_error_de_validacion(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['Pregunta recuperada', '   '],
                'version' => 0,
            ])
            ->assertSessionHasErrors('preguntas.1');

        $response = $this->actingAs($this->datos['users']['director'])
            ->get($this->rutaDirector())
            ->assertOk()
            ->assertSee('Pregunta recuperada');

        $preguntasIniciales = $this->extraerPreguntasIniciales($response->getContent());

        $this->assertSame([
            ['texto' => 'Pregunta recuperada'],
            ['texto' => ''],
        ], $preguntasIniciales);
        $this->assertSame(0, DB::table('investigacion_formativa.encuesta')->count());
    }

    public function test_un_guardado_desactualizado_conserva_el_intento_y_no_sobrescribe_el_borrador_actual(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['Pregunta guardada inicialmente'],
                'version' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['Pregunta actual del servidor'],
                'version' => 1,
            ])
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['Pregunta intentada en la pestaña obsoleta'],
                'version' => 1,
            ])
            ->assertStatus(409)
            ->assertSee('La encuesta cambió en otra sesión.')
            ->assertSee('Pregunta actual del servidor')
            ->assertSee('Pregunta intentada en la pestaña obsoleta')
            ->assertSee('Versión enviada: 1.')
            ->assertSee('Versión guardada actualmente: 2.')
            ->assertDontSee('Guardar borrador')
            ->assertDontSee('Publicar encuesta')
            ->assertDontSee('Cerrar encuesta');

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', 9901)
            ->first();

        $this->assertSame(2, (int) $encuesta->version);
        $this->assertSame(
            ['Pregunta actual del servidor'],
            DB::table('investigacion_formativa.encuesta_pregunta')
                ->where('id_encuesta', $encuesta->id_encuesta)
                ->orderBy('orden')
                ->pluck('texto')
                ->all(),
        );
    }

    public function test_la_publicacion_exige_preguntas_guardadas_y_luego_fija_el_contenido(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->post($this->rutaPublicar(), ['version' => 1])
            ->assertNotFound();

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['Pregunta publicada'],
                'version' => 0,
            ])
            ->assertSessionHasNoErrors();

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', 9901)
            ->first();

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->post($this->rutaPublicar(), ['version' => 1])
            ->assertRedirect($this->rutaDirector())
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'publicada',
            DB::table('investigacion_formativa.encuesta')
                ->where('id_encuesta', $encuesta->id_encuesta)
                ->value('estado'),
        );

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['Cambio no permitido'],
                'version' => 2,
            ])
            ->assertRedirect($this->rutaDirector())
            ->assertSessionHasErrors('encuesta');

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->post($this->rutaPublicar(), ['version' => 2])
            ->assertSessionHasErrors('encuesta');

        $this->assertSame(
            ['Pregunta publicada'],
            DB::table('investigacion_formativa.encuesta_pregunta')
                ->where('id_encuesta', $encuesta->id_encuesta)
                ->orderBy('orden')
                ->pluck('texto')
                ->all(),
        );

        $this->actingAs($this->datos['users']['director'])
            ->get($this->rutaDirector())
            ->assertOk()
            ->assertSee('Las preguntas publicadas son definitivas')
            ->assertDontSee('Guardar borrador');
    }

    public function test_una_encuesta_se_cierra_sin_reabrir_y_el_mensaje_bloquea_nuevas_respuestas(): void
    {
        $encuesta = $this->crearEncuestaPublicada();

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->post($this->rutaCerrar(), ['version' => 1])
            ->assertStatus(409)
            ->assertDontSee('Cerrar encuesta');

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->post($this->rutaCerrar(), ['version' => 2])
            ->assertRedirect($this->rutaDirector())
            ->assertSessionHas('status', 'La encuesta se cerró y ya no admite nuevas respuestas.');

        $this->assertSame(
            'cerrada',
            DB::table('investigacion_formativa.encuesta')
                ->where('id_encuesta', $encuesta['id_encuesta'])
                ->value('estado'),
        );
        $this->assertSame(
            3,
            (int) DB::table('investigacion_formativa.encuesta')
                ->where('id_encuesta', $encuesta['id_encuesta'])
                ->value('version'),
        );

        $this->actingAs($this->datos['users']['director'])
            ->get($this->rutaDirector())
            ->assertOk()
            ->assertDontSee('Cerrar encuesta')
            ->assertDontSee('Publicar encuesta');

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->post($this->rutaPublicar(), ['version' => 3])
            ->assertSessionHasErrors('encuesta');

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $this->payloadRespuesta($encuesta, 4))
            ->assertSessionHasErrors('encuesta');

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta')->count());
    }

    public function test_el_estudiante_ve_las_preguntas_y_guarda_una_sola_respuesta(): void
    {
        $encuesta = $this->crearEncuestaPublicada();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get($this->rutaAlumno())
            ->assertOk()
            ->assertSee('Pregunta uno')
            ->assertSee('Pregunta dos')
            ->assertSee('Muy en desacuerdo')
            ->assertSee('Muy de acuerdo')
            ->assertSee('Tus respuestas son confidenciales.')
            ->assertSee('no se presenta como anónima.');

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), [
                'respuestas' => [
                    ['id_pregunta' => $encuesta['preguntas'][0]['id_pregunta'], 'valor' => 5],
                    ['id_pregunta' => $encuesta['preguntas'][1]['id_pregunta'], 'valor' => 3],
                ],
                'comentario' => 'Comentario confidencial.',
            ])
            ->assertRedirect($this->rutaAlumno())
            ->assertSessionHas('status', 'Tu respuesta quedó registrada correctamente.');

        $respuesta = DB::table('investigacion_formativa.encuesta_respuesta')
            ->where('id_encuesta', $encuesta['id_encuesta'])
            ->where('id_estudiante', 9901)
            ->first();

        $this->assertNotNull($respuesta);
        $this->assertSame('Comentario confidencial.', $respuesta->comentario);
        $this->assertSame(
            [5, 3],
            DB::table('investigacion_formativa.encuesta_respuesta_item')
                ->where('id_respuesta', $respuesta->id_respuesta)
                ->orderBy('id_pregunta')
                ->pluck('valor')
                ->map(static fn (mixed $valor): int => (int) $valor)
                ->all(),
        );

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get($this->rutaAlumno())
            ->assertOk()
            ->assertSee('Respuesta registrada')
            ->assertSee('Confirmación recibida')
            ->assertSee('Comentario enviado');

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), [
                'respuestas' => [
                    ['id_pregunta' => $encuesta['preguntas'][0]['id_pregunta'], 'valor' => 1],
                    ['id_pregunta' => $encuesta['preguntas'][1]['id_pregunta'], 'valor' => 2],
                ],
                'comentario' => 'Comentario que no debe reemplazar el original.',
            ])
            ->assertRedirect($this->rutaAlumno())
            ->assertSessionHas('status', 'Ya enviaste tu respuesta. Se conserva la respuesta registrada.');

        $this->assertSame(1, DB::table('investigacion_formativa.encuesta_respuesta')->count());
        $this->assertSame(2, DB::table('investigacion_formativa.encuesta_respuesta_item')->count());
        $this->assertSame(
            'Comentario confidencial.',
            DB::table('investigacion_formativa.encuesta_respuesta')
                ->where('id_encuesta', $encuesta['id_encuesta'])
                ->value('comentario'),
        );
    }

    public function test_rechaza_respuestas_incompletas_extra_o_repetidas(): void
    {
        $encuesta = $this->crearEncuestaPublicada();
        $primera = $encuesta['preguntas'][0]['id_pregunta'];
        $segunda = $encuesta['preguntas'][1]['id_pregunta'];

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), [
                'respuestas' => [
                    ['id_pregunta' => $primera, 'valor' => 4],
                ],
            ])
            ->assertSessionHasErrors('encuesta');

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), [
                'respuestas' => [
                    ['id_pregunta' => $primera, 'valor' => 4],
                    ['id_pregunta' => $segunda, 'valor' => 3],
                    ['id_pregunta' => 999999, 'valor' => 2],
                ],
            ])
            ->assertSessionHasErrors('encuesta');

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), [
                'respuestas' => [
                    ['id_pregunta' => $primera, 'valor' => 4],
                    ['id_pregunta' => $primera, 'valor' => 3],
                ],
            ])
            ->assertSessionHasErrors('respuestas.1.id_pregunta');

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta')->count());
        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta_item')->count());
    }

    public function test_el_comentario_es_opcional_y_limita_a_dos_mil_caracteres(): void
    {
        $encuesta = $this->crearEncuestaPublicada();
        $payload = $this->payloadRespuesta($encuesta, 4);
        $payload['comentario'] = str_repeat('a', 2001);

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $payload)
            ->assertSessionHasErrors('comentario');

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta')->count());

        $payload['comentario'] = str_repeat('b', 2000);
        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $payload)
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2000,
            mb_strlen((string) DB::table('investigacion_formativa.encuesta_respuesta')
                ->where('id_encuesta', $encuesta['id_encuesta'])
                ->value('comentario')),
        );
    }

    public function test_las_respuestas_enviadas_siguen_visibles_tras_deshabilitar_la_seccion(): void
    {
        $encuesta = $this->crearEncuestaPublicada();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $this->payloadRespuesta($encuesta, 5))
            ->assertSessionHasNoErrors();

        DB::table('investigacion_formativa.seccion_configuracion')
            ->where('id_seccion', 9901)
            ->update(['habilitada' => false]);

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get($this->rutaAlumno())
            ->assertOk()
            ->assertSee('Respuesta registrada');

        $this->actingAs($this->datos['users']['alumno_b'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $this->payloadRespuesta($encuesta, 3))
            ->assertSessionHasErrors('encuesta');

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $this->payloadRespuesta($encuesta, 1))
            ->assertSessionHas('status', 'Ya enviaste tu respuesta. Se conserva la respuesta registrada.');

        $response = $this->actingAs($this->datos['users']['director'])
            ->get($this->rutaDirector())
            ->assertOk();
        $texto = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));
        $this->assertStringContainsString('1 respuesta registrada', $texto);

        $this->assertSame(1, DB::table('investigacion_formativa.encuesta_respuesta')->count());
        $this->assertSame(2, DB::table('investigacion_formativa.encuesta_respuesta_item')->count());
    }

    public function test_un_estudiante_retirado_no_puede_responder(): void
    {
        $encuesta = $this->crearEncuestaPublicada();

        DB::table('investigacion_formativa.participante_seccion')
            ->where('id_seccion', 9901)
            ->where('id_estudiante', 9901)
            ->update(['estado' => 'retirado']);

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get($this->rutaAlumno())
            ->assertForbidden();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $this->payloadRespuesta($encuesta, 4))
            ->assertForbidden();

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta')->count());
    }

    public function test_la_cabecera_y_los_items_de_una_respuesta_se_guardan_en_una_sola_transaccion(): void
    {
        $encuesta = $this->crearEncuestaPublicada();
        $idPreguntaConFallo = $encuesta['preguntas'][1]['id_pregunta'];

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION if_test_fallar_item_encuesta()
            RETURNS trigger
            LANGUAGE plpgsql
            AS \$funcion\$
            BEGIN
                IF NEW.id_pregunta = {$idPreguntaConFallo} THEN
                    RAISE EXCEPTION 'Falla controlada del item de encuesta.'
                    USING ERRCODE = '23514';
                END IF;

                RETURN NEW;
            END;
            \$funcion\$;
            SQL);
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER if_test_fallar_item_encuesta_trigger
            BEFORE INSERT ON investigacion_formativa.encuesta_respuesta_item
            FOR EACH ROW
            EXECUTE FUNCTION if_test_fallar_item_encuesta();
            SQL);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->datos['users']['alumno_a'])
                ->from($this->rutaAlumno())
                ->post($this->rutaAlumno(), $this->payloadRespuesta($encuesta, 4));

            $this->fail('La restricción de prueba debió interrumpir la respuesta.');
        } catch (QueryException $exception) {
            $this->assertSame('23514', $exception->errorInfo[0] ?? null);
        }

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta')->count());
        $this->assertSame(0, DB::table('investigacion_formativa.encuesta_respuesta_item')->count());
    }

    public function test_las_restricciones_de_la_base_impiden_duplicados_y_respuestas_cruzadas(): void
    {
        $encuesta = $this->crearEncuestaPublicada();
        $idEncuesta = $encuesta['id_encuesta'];
        $idPregunta = $encuesta['preguntas'][0]['id_pregunta'];

        $restricciones = DB::select(<<<'SQL'
            SELECT conname
            FROM pg_constraint
            WHERE conname IN (
                'if_encuesta_seccion_uq',
                'if_encuesta_estado_ck',
                'if_encuesta_version_ck',
                'if_encuesta_pregunta_orden_uq',
                'if_encuesta_pregunta_orden_ck',
                'if_encuesta_respuesta_estudiante_uq',
                'if_encuesta_respuesta_item_pk',
                'if_encuesta_respuesta_item_respuesta_fk',
                'if_encuesta_respuesta_item_pregunta_fk',
                'if_encuesta_respuesta_item_valor_ck'
            )
            SQL);

        $this->assertCount(10, $restricciones);

        $idRespuesta = DB::table('investigacion_formativa.encuesta_respuesta')->insertGetId([
            'id_encuesta' => $idEncuesta,
            'id_estudiante' => 9901,
            'comentario' => null,
        ], 'id_respuesta');

        $this->assertSqlFails('23505', function () use ($idEncuesta): void {
            DB::table('investigacion_formativa.encuesta_respuesta')->insert([
                'id_encuesta' => $idEncuesta,
                'id_estudiante' => 9901,
                'comentario' => 'Duplicada.',
            ]);
        });

        $this->assertSqlFails('23514', function () use ($idRespuesta, $idPregunta, $idEncuesta): void {
            DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                'id_respuesta' => $idRespuesta,
                'id_pregunta' => $idPregunta,
                'id_encuesta' => $idEncuesta,
                'valor' => 0,
            ]);
        });

        $idEncuestaOtra = DB::table('investigacion_formativa.encuesta')->insertGetId([
            'id_seccion' => 9902,
            'estado' => 'publicada',
            'creada_por' => $this->datos['users']['director']->id,
            'publicada_en' => now('UTC'),
            'version' => 2,
        ], 'id_encuesta');

        $idPreguntaOtra = DB::table('investigacion_formativa.encuesta_pregunta')->insertGetId([
            'id_encuesta' => $idEncuestaOtra,
            'texto' => 'Pregunta de otra encuesta.',
            'orden' => 1,
        ], 'id_pregunta');

        $this->assertSqlFails('23503', function () use ($idRespuesta, $idPreguntaOtra, $idEncuesta): void {
            DB::table('investigacion_formativa.encuesta_respuesta_item')->insert([
                'id_respuesta' => $idRespuesta,
                'id_pregunta' => $idPreguntaOtra,
                'id_encuesta' => $idEncuesta,
                'valor' => 3,
            ]);
        });

        DB::table('investigacion_formativa.encuesta_pregunta')
            ->where('id_pregunta', $idPreguntaOtra)
            ->delete();
        DB::table('investigacion_formativa.encuesta')
            ->where('id_encuesta', $idEncuestaOtra)
            ->delete();

        $idUsuario = $this->datos['users']['director']->id;
        $this->assertSqlFails('23514', function () use ($idUsuario): void {
            DB::table('investigacion_formativa.encuesta')->insert([
                'id_seccion' => 9902,
                'estado' => 'reabierta',
                'creada_por' => $idUsuario,
                'version' => 1,
            ]);
        });
    }

    public function test_el_panel_directorial_muestra_el_vacio_y_no_expone_nombres_de_respondentes(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get($this->rutaDirector())
            ->assertOk()
            ->assertSee('Todavía no hay respuestas registradas.')
            ->assertDontSee('Alumna Prueba')
            ->assertDontSee('Promedio')
            ->assertDontSee('Distribución');

        $encuesta = $this->crearEncuestaPublicada();
        $this->actingAs($this->datos['users']['alumno_a'])
            ->from($this->rutaAlumno())
            ->post($this->rutaAlumno(), $this->payloadRespuesta($encuesta, 4))
            ->assertSessionHasNoErrors();

        $response = $this->actingAs($this->datos['users']['director'])
            ->get($this->rutaDirector())
            ->assertOk()
            ->assertDontSee('Alumna Prueba')
            ->assertDontSee('Alumno Prueba');
        $texto = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));
        $this->assertStringContainsString('1 respuesta registrada', $texto);
    }

    public function test_las_rutas_de_encuesta_mantienen_los_limites_de_rol_y_de_escuela(): void
    {
        $this->actingAs($this->datos['users']['docente_a'])
            ->get($this->rutaDirector())
            ->assertForbidden();

        $this->actingAs($this->datos['users']['alumno_a'])
            ->get($this->rutaDirector())
            ->assertForbidden();

        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.encuestas.edit', 9902))
            ->assertForbidden();

        $this->actingAs($this->datos['users']['alumno_otra_escuela'])
            ->get($this->rutaAlumno())
            ->assertForbidden();
    }

    /**
     * @return array{id_encuesta: int, preguntas: list<array{id_pregunta: int, texto: string, orden: int}>}
     */
    private function crearEncuestaPublicada(): array
    {
        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->put($this->rutaDirector(), [
                'preguntas' => ['Pregunta uno', 'Pregunta dos'],
                'version' => 0,
            ])
            ->assertSessionHasNoErrors();

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', 9901)
            ->first();

        $this->actingAs($this->datos['users']['director'])
            ->from($this->rutaDirector())
            ->post($this->rutaPublicar(), ['version' => 1])
            ->assertSessionHasNoErrors();

        $preguntas = DB::table('investigacion_formativa.encuesta_pregunta')
            ->where('id_encuesta', $encuesta->id_encuesta)
            ->orderBy('orden')
            ->get()
            ->map(static fn (object $pregunta): array => [
                'id_pregunta' => (int) $pregunta->id_pregunta,
                'texto' => (string) $pregunta->texto,
                'orden' => (int) $pregunta->orden,
            ])
            ->all();

        return [
            'id_encuesta' => (int) $encuesta->id_encuesta,
            'preguntas' => $preguntas,
        ];
    }

    /**
     * @return list<array{texto: string}>
     */
    private function extraerPreguntasIniciales(string $html): array
    {
        $contenido = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $this->assertSame(
            1,
            preg_match(
                '/x-data="\{ preguntas: JSON\.parse\(\'(.*)\'\) \}"/',
                $contenido,
                $coincidencias,
            ),
            'La expresión Alpine inicial no usa la serialización JSON.parse de Laravel.',
        );

        $json = json_decode(
            '"'.$coincidencias[1].'"',
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $preguntas = json_decode(
            $json,
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertIsArray($preguntas);

        return $preguntas;
    }

    /**
     * @param  array{id_encuesta: int, preguntas: list<array{id_pregunta: int, texto: string, orden: int}>}  $encuesta
     * @return array{respuestas: list<array{id_pregunta: int, valor: int}>}
     */
    private function payloadRespuesta(array $encuesta, int $valor): array
    {
        return [
            'respuestas' => array_map(
                static fn (array $pregunta): array => [
                    'id_pregunta' => $pregunta['id_pregunta'],
                    'valor' => $valor,
                ],
                $encuesta['preguntas'],
            ),
        ];
    }

    private function assertSqlFails(string $sqlState, callable $operacion): void
    {
        try {
            DB::transaction($operacion);
            $this->fail('La operación debió ser rechazada por PostgreSQL.');
        } catch (QueryException $exception) {
            $this->assertSame($sqlState, $exception->errorInfo[0] ?? null);
        }
    }

    private function rutaDirector(?int $idSeccion = null): string
    {
        return route('investigacion-formativa.director.encuestas.edit', $idSeccion ?? 9901);
    }

    private function rutaPublicar(?int $idSeccion = null): string
    {
        return route('investigacion-formativa.director.encuestas.publicar', $idSeccion ?? 9901);
    }

    private function rutaCerrar(?int $idSeccion = null): string
    {
        return route('investigacion-formativa.director.encuestas.cerrar', $idSeccion ?? 9901);
    }

    private function rutaAlumno(?int $idSeccion = null): string
    {
        return route('investigacion-formativa.alumno.encuestas.show', $idSeccion ?? 9901);
    }
}
