<?php

namespace Tests\Feature\InvestigacionFormativa;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\TestCase;

final class SecretariaEncuestaTest extends TestCase
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

    public function test_la_secretaria_ve_solo_las_secciones_habilitadas_de_su_escuela(): void
    {
        $response = $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->get(route('investigacion-formativa.secretaria.index'))
            ->assertOk()
            ->assertSee('Ingeniería Informática')
            ->assertDontSee('Ingeniería de Sistemas')
            ->assertSee('IF-501')
            ->assertDontSee('IF-502');

        $this->assertStringContainsString(
            route('investigacion-formativa.secretaria.encuestas.edit', 9901),
            $response->getContent(),
        );
        $this->assertStringNotContainsString(
            route('investigacion-formativa.secretaria.encuestas.edit', 9902),
            $response->getContent(),
        );
    }

    public function test_la_secretaria_sin_asignacion_ve_el_estado_vacio(): void
    {
        $this->actingAs($this->datos['users']['secretaria_sin_asignar'])
            ->get(route('investigacion-formativa.secretaria.index'))
            ->assertOk()
            ->assertSee('No hay secciones habilitadas en sus escuelas asignadas.')
            ->assertDontSee('Escuelas asignadas');
    }

    public function test_la_secretaria_prepara_y_publica_la_encuesta_de_su_escuela(): void
    {
        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria())
            ->put($this->rutaSecretaria(), [
                'preguntas' => ['Pregunta preparada por la secretaría'],
                'version' => 0,
            ])
            ->assertRedirect($this->rutaSecretaria())
            ->assertSessionHasNoErrors();

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', 9901)
            ->first();

        $this->assertNotNull($encuesta);
        $this->assertSame('borrador', $encuesta->estado);
        $this->assertSame(1, (int) $encuesta->version);
        $this->assertSame(
            $this->datos['users']['secretaria_asignada']->id,
            (int) $encuesta->creada_por,
        );

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaPublicarSecretaria())
            ->post($this->rutaPublicarSecretaria(), ['version' => 1])
            ->assertRedirect($this->rutaSecretaria())
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'publicada',
            DB::table('investigacion_formativa.encuesta')
                ->where('id_seccion', 9901)
                ->value('estado'),
        );

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->get($this->rutaSecretaria())
            ->assertOk()
            ->assertSee('Publicada')
            ->assertSee('Las preguntas publicadas son definitivas')
            ->assertDontSee('Guardar borrador')
            ->assertDontSee('Publicar encuesta');
    }

    public function test_el_estudiante_responde_y_la_direccion_ve_el_resultado_agregado(): void
    {
        $this->crearEncuestaPublicadaPorSecretaria();

        $preguntas = DB::table('investigacion_formativa.encuesta_pregunta')
            ->orderBy('orden')
            ->get(['id_pregunta']);

        $this->actingAs($this->datos['users']['alumno_a'])
            ->from(route('investigacion-formativa.alumno.encuestas.show', 9901))
            ->post(route('investigacion-formativa.alumno.encuestas.store', 9901), [
                'respuestas' => $preguntas
                    ->map(static fn (object $pregunta): array => [
                        'id_pregunta' => (int) $pregunta->id_pregunta,
                        'valor' => 4,
                    ])
                    ->all(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            1,
            DB::table('investigacion_formativa.encuesta_respuesta')->count(),
        );

        $response = $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.encuestas.edit', 9901))
            ->assertOk();

        $texto = preg_replace('/\s+/', ' ', strip_tags($response->getContent()));

        $this->assertStringContainsString('1 respuesta registrada', $texto);
    }

    public function test_la_secretaria_no_accede_a_la_seccion_de_otra_escuela(): void
    {
        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->get(route('investigacion-formativa.secretaria.encuestas.edit', 9902))
            ->assertForbidden();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria(9902))
            ->put($this->rutaSecretaria(9902), [
                'preguntas' => ['Intento fuera de alcance'],
                'version' => 0,
            ])
            ->assertForbidden();

        $this->actingAs($this->datos['users']['secretaria_otra_escuela'])
            ->get(route('investigacion-formativa.secretaria.encuestas.edit', 9901))
            ->assertForbidden();

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta')->count());
    }

    public function test_la_secretaria_no_puede_cerrar_la_encuesta(): void
    {
        $this->crearEncuestaPublicadaPorSecretaria();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from(route('investigacion-formativa.director.encuestas.cerrar', 9901))
            ->post(route('investigacion-formativa.director.encuestas.cerrar', 9901), ['version' => 2])
            ->assertForbidden();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->get($this->rutaSecretaria())
            ->assertOk()
            ->assertDontSee('Cerrar encuesta');

        $this->assertSame(
            'publicada',
            DB::table('investigacion_formativa.encuesta')
                ->where('id_seccion', 9901)
                ->value('estado'),
        );
    }

    public function test_un_guardado_desactualizado_de_la_secretaria_devuelve_conflicto(): void
    {
        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria())
            ->put($this->rutaSecretaria(), [
                'preguntas' => ['Pregunta guardada inicialmente'],
                'version' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria())
            ->put($this->rutaSecretaria(), [
                'preguntas' => ['Pregunta actual del servidor'],
                'version' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria())
            ->put($this->rutaSecretaria(), [
                'preguntas' => ['Pregunta intentada en la pestaña obsoleta'],
                'version' => 1,
            ])
            ->assertStatus(409)
            ->assertSee('La encuesta cambió en otra sesión.')
            ->assertSee('Pregunta actual del servidor')
            ->assertSee('Pregunta intentada en la pestaña obsoleta')
            ->assertSee('Versión enviada: 1.')
            ->assertSee('Versión guardada actualmente: 2.');

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

    public function test_la_publicacion_desactualizada_de_la_secretaria_devuelve_conflicto(): void
    {
        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria())
            ->put($this->rutaSecretaria(), [
                'preguntas' => ['Pregunta inicial'],
                'version' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria())
            ->put($this->rutaSecretaria(), [
                'preguntas' => ['Pregunta actual del servidor'],
                'version' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaPublicarSecretaria())
            ->post($this->rutaPublicarSecretaria(), ['version' => 1])
            ->assertStatus(409)
            ->assertSee('La encuesta cambió en otra sesión.');

        $this->assertSame(
            'borrador',
            DB::table('investigacion_formativa.encuesta')
                ->where('id_seccion', 9901)
                ->value('estado'),
        );
    }

    public function test_un_cierre_desactualizado_de_la_direccion_devuelve_conflicto(): void
    {
        $this->crearEncuestaPublicadaPorSecretaria();

        $this->actingAs($this->datos['users']['director'])
            ->from(route('investigacion-formativa.director.encuestas.cerrar', 9901))
            ->post(route('investigacion-formativa.director.encuestas.cerrar', 9901), ['version' => 1])
            ->assertStatus(409)
            ->assertSee('La encuesta cambió en otra sesión.')
            ->assertDontSee('Cerrar encuesta');

        $this->assertSame(
            'publicada',
            DB::table('investigacion_formativa.encuesta')
                ->where('id_seccion', 9901)
                ->value('estado'),
        );
    }

    public function test_un_usuario_multirrol_prepara_y_publica_en_la_escuela_de_secretaria(): void
    {
        $this->configurarSeccionCompleta(9902);

        $this->actingAs($this->datos['users']['multisecretaria'])
            ->from($this->rutaSecretaria(9902))
            ->put($this->rutaSecretaria(9902), [
                'preguntas' => ['Pregunta de la escuela asignada como secretaría'],
                'version' => 0,
            ])
            ->assertRedirect($this->rutaSecretaria(9902))
            ->assertSessionHasNoErrors();

        $encuesta = DB::table('investigacion_formativa.encuesta')
            ->where('id_seccion', 9902)
            ->first();

        $this->assertNotNull($encuesta);
        $this->assertSame('borrador', $encuesta->estado);

        $this->actingAs($this->datos['users']['multisecretaria'])
            ->from($this->rutaPublicarSecretaria(9902))
            ->post($this->rutaPublicarSecretaria(9902), ['version' => 1])
            ->assertRedirect($this->rutaSecretaria(9902))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            'publicada',
            DB::table('investigacion_formativa.encuesta')
                ->where('id_seccion', 9902)
                ->value('estado'),
        );
    }

    public function test_un_usuario_multirrol_no_prepara_en_una_escuela_no_relacionada(): void
    {
        // El usuario solo conserva el alcance de secretaría en la escuela 921.
        DB::table('investigacion_formativa.director_escuela')
            ->where('user_id', $this->datos['users']['multisecretaria']->id)
            ->delete();

        $this->actingAs($this->datos['users']['multisecretaria'])
            ->get(route('investigacion-formativa.secretaria.encuestas.edit', 9901))
            ->assertForbidden();

        $this->actingAs($this->datos['users']['multisecretaria'])
            ->from($this->rutaSecretaria(9901))
            ->put($this->rutaSecretaria(9901), [
                'preguntas' => ['Intento fuera del alcance de secretaría'],
                'version' => 0,
            ])
            ->assertForbidden();

        $this->assertSame(0, DB::table('investigacion_formativa.encuesta')->count());
    }

    public function test_el_espacio_de_secretaria_solo_aparece_para_usuarios_con_el_rol(): void
    {
        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->get(route('investigacion-formativa.index'))
            ->assertRedirect(route('investigacion-formativa.secretaria.index'));

        $this->actingAs($this->datos['users']['multisecretaria'])
            ->get(route('investigacion-formativa.index'))
            ->assertOk()
            ->assertSee('Secretaría de escuela')
            ->assertSee('Dirección de escuela');
    }

    private function crearEncuestaPublicadaPorSecretaria(): void
    {
        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaSecretaria())
            ->put($this->rutaSecretaria(), [
                'preguntas' => ['Pregunta publicada por la secretaría'],
                'version' => 0,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->datos['users']['secretaria_asignada'])
            ->from($this->rutaPublicarSecretaria())
            ->post($this->rutaPublicarSecretaria(), ['version' => 1])
            ->assertSessionHasNoErrors();
    }

    private function rutaSecretaria(?int $idSeccion = null): string
    {
        return route('investigacion-formativa.secretaria.encuestas.edit', $idSeccion ?? 9901);
    }

    private function rutaPublicarSecretaria(?int $idSeccion = null): string
    {
        return route('investigacion-formativa.secretaria.encuestas.publicar', $idSeccion ?? 9901);
    }
}
