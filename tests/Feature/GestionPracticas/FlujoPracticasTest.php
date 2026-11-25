<?php

namespace Tests\Feature\GestionPracticas;

use App\Models\User;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\CriterioEvaluacion;
use App\Modules\GestionPracticas\Models\DocumentoPractica;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Recorre el proceso completo de los cuatro actores, tal como lo define el diagrama de procesos. */
final class FlujoPracticasTest extends PracticasTestCase
{
    public function test_proceso_completo_desde_la_convocatoria_hasta_el_cierre(): void
    {
        // Empresa: crea la convocatoria y la envía a revisión.
        $this->como('empresa')->post(route('gestion-practicas.empresa.convocatorias.store'), $this->datosConvocatoria())
            ->assertSessionHasNoErrors()->assertRedirect();
        $convocatoria = Convocatoria::where('titulo', 'Analista de calidad de software')->firstOrFail();
        $this->assertSame(Convocatoria::BORRADOR, $convocatoria->estado);

        $this->post(route('gestion-practicas.empresa.convocatorias.enviar', $convocatoria))->assertSessionHasNoErrors();
        $this->assertSame(Convocatoria::EN_REVISION, $convocatoria->refresh()->estado);

        // Administrador: observa; la empresa corrige y reenvía.
        $this->como('admin')->post(route('gestion-practicas.revision.convocatorias.observar', $convocatoria), [
            'descripcion' => 'Falta precisar las herramientas requeridas.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(Convocatoria::OBSERVADA, $convocatoria->refresh()->estado);

        $this->como('empresa')->put(route('gestion-practicas.empresa.convocatorias.update', $convocatoria), $this->datosConvocatoria([
            'requisitos' => 'Conocimientos de pruebas automatizadas y Git.',
        ]))->assertSessionHasNoErrors();
        $this->post(route('gestion-practicas.empresa.convocatorias.enviar', $convocatoria))->assertSessionHasNoErrors();
        $this->assertSame('subsanada', $convocatoria->observaciones()->first()->estado);

        // Administrador: aprueba y publica.
        $this->como('admin')->post(route('gestion-practicas.revision.convocatorias.aprobar', $convocatoria))->assertSessionHasNoErrors();
        $convocatoria->refresh();
        $this->assertSame(Convocatoria::PUBLICADA, $convocatoria->estado);
        $this->assertNotNull($convocatoria->publicada_por);

        // Estudiante: ve la convocatoria, postula y es seleccionado.
        $this->como('estudiante')->get(route('gestion-practicas.estudiante.convocatorias.index'))
            ->assertOk()->assertSee('Analista de calidad de software');
        $this->get(route('gestion-practicas.estudiante.convocatorias.show', $convocatoria))->assertOk();
        $this->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria))->assertSessionHasNoErrors();

        $postulacion = Postulacion::where('id_convocatoria', $convocatoria->id_convocatoria)->firstOrFail();
        $this->post(route('gestion-practicas.estudiante.postulaciones.resultado', $postulacion), ['resultado' => 'seleccionado'])
            ->assertRedirect(route('gestion-practicas.estudiante.practicas.create', $postulacion));

        // Estudiante: registra la práctica con la carta de aceptación y la envía.
        $this->post(route('gestion-practicas.estudiante.practicas.store', $postulacion), $this->datosPractica())
            ->assertSessionHasNoErrors();
        $practica = Practica::firstOrFail();
        $this->assertSame(Practica::REGISTRADA, $practica->estado);
        $this->assertSame($postulacion->id_postulacion, $postulacion->refresh()->id_postulacion);
        $this->assertEquals($practica->id_practica, $postulacion->id_practica);

        $this->post(route('gestion-practicas.estudiante.practicas.enviar', $practica))->assertSessionHasNoErrors();
        $this->assertSame(Practica::EN_REVISION, $practica->refresh()->estado);

        // Administrador: observa; el estudiante corrige y reenvía.
        $this->como('admin')->post(route('gestion-practicas.revision.practicas.observar', $practica), [
            'descripcion' => 'Las fechas no coinciden con la carta.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(Practica::OBSERVADA, $practica->refresh()->estado);

        $this->como('estudiante')->put(route('gestion-practicas.estudiante.practicas.update', $practica), $this->datosPractica([
            'fecha_inicio' => now()->addDays(5)->toDateString(),
        ], false))->assertSessionHasNoErrors();
        $this->post(route('gestion-practicas.estudiante.practicas.enviar', $practica))->assertSessionHasNoErrors();

        // Administrador: valida, asigna al docente supervisor y la práctica queda en curso.
        $this->como('admin')->post(route('gestion-practicas.revision.practicas.validar', $practica), [
            'id_docente' => $this->idDocente(),
        ])->assertSessionHasNoErrors();
        $practica->refresh();
        $this->assertSame(Practica::EN_CURSO, $practica->estado);
        $this->assertEquals($this->idDocente(), $practica->id_docente_supervisor);
        $this->assertNotNull($practica->validado_por);

        // Estudiante: sube el informe final.
        $this->como('estudiante')->post(route('gestion-practicas.documentos.store', $practica), [
            'tipo_documento' => 'informe_final',
            'archivo' => UploadedFile::fake()->create('informe.pdf', 200, 'application/pdf'),
        ])->assertSessionHasNoErrors();
        $informe = DocumentoPractica::where('tipo_documento', 'informe_final')->firstOrFail();

        // Supervisor: ve la práctica, hace seguimiento, aprueba el informe, evalúa y cierra.
        $this->como('supervisor')->get(route('gestion-practicas.supervisor.practicas.index'))->assertOk();
        $this->get(route('gestion-practicas.supervisor.practicas.show', $practica))->assertOk();

        $this->post(route('gestion-practicas.supervisor.practicas.seguimiento', $practica), [
            'fecha' => now()->toDateString(), 'tipo' => 'reunion', 'descripcion' => 'Reunión de avance con el estudiante.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, $practica->seguimientos()->count());

        $this->post(route('gestion-practicas.documentos.revisar', $informe), ['estado' => 'aprobado'])->assertSessionHasNoErrors();

        $notas = CriterioEvaluacion::pluck('id_criterio')->mapWithKeys(fn ($id) => [$id => 16])->all();
        $this->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), [
            'notas' => $notas, 'comentario' => 'Buen desempeño.',
        ])->assertSessionHasNoErrors();

        $this->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica))->assertSessionHasNoErrors();
        $practica->refresh();
        $this->assertSame(Practica::FINALIZADA, $practica->estado);
        $this->assertEquals(16, $practica->nota_final);
        $this->assertSame('aprobado', $practica->resultado);
        $this->assertNotNull($practica->cerrada_por);
    }

    public function test_la_empresa_nueva_creada_por_el_administrador_puede_operar(): void
    {
        $this->como('admin')->post(route('gestion-practicas.empresas.store'), [
            'ruc' => '20999888777', 'razon_social' => 'Redes del Norte S.A.C.', 'contacto_dni' => '71234567',
            'contacto_nombres' => 'Luis', 'contacto_apellido_paterno' => 'Rojas',
            'correo_acceso' => 'rrhh@redesnorte.test', 'password' => 'Segura2026!',
        ])->assertSessionHasNoErrors()->assertRedirect(route('gestion-practicas.empresas.index'));

        $this->get(route('gestion-practicas.empresas.index'))->assertOk()->assertSee('Redes del Norte');

        $this->actingAs(User::where('email', 'rrhh@redesnorte.test')->firstOrFail())
            ->post(route('gestion-practicas.empresa.convocatorias.store'), $this->datosConvocatoria())
            ->assertSessionHasNoErrors();

        $this->assertSame(
            (int) DB::table('comun.empresa')->where('ruc', '20999888777')->value('id_empresa'),
            (int) Convocatoria::where('titulo', 'Analista de calidad de software')->value('id_empresa'),
        );
    }

    /** @param array<string, mixed> $cambios */
    private function datosConvocatoria(array $cambios = []): array
    {
        return array_merge([
            'titulo' => 'Analista de calidad de software',
            'descripcion' => 'Diseño y ejecución de pruebas funcionales.',
            'requisitos' => 'Conocimientos de pruebas.',
            'enlace_postulacion' => 'https://empresa.test/practicas/qa',
            'tipo_practica' => 'preprofesional',
            'modalidad' => 'remota',
            'vacantes' => 1,
            'fecha_cierre' => now()->addDays(20)->toDateString(),
        ], $cambios);
    }

    /** @param array<string, mixed> $cambios */
    private function datosPractica(array $cambios = [], bool $conCarta = true): array
    {
        return array_merge([
            'area' => 'Calidad de software',
            'funciones' => 'Pruebas funcionales y reporte de defectos.',
            'fecha_inicio' => now()->addDays(2)->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(),
            'horas_totales' => 240,
        ], $conCarta ? ['carta' => UploadedFile::fake()->create('carta.pdf', 100, 'application/pdf')] : [], $cambios);
    }
}
