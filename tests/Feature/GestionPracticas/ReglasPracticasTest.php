<?php

namespace Tests\Feature\GestionPracticas;

use App\Models\User;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\CriterioEvaluacion;
use App\Modules\GestionPracticas\Models\DocumentoPractica;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Services\EvaluacionService;
use App\Modules\GestionPracticas\Services\PracticaService;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reglas de negocio y transiciones de estado que impiden saltarse el proceso. */
final class ReglasPracticasTest extends PracticasTestCase
{
    public function test_no_se_puede_postular_dos_veces_ni_a_una_convocatoria_cerrada(): void
    {
        $convocatoria = Convocatoria::firstOrFail();

        $this->como('estudiante')->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria))
            ->assertSessionHasNoErrors();
        $this->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria))->assertSessionHasErrors('convocatoria');
        $this->assertSame(1, Postulacion::count());

        // Publicada pero con la fecha de cierre vencida: ya no admite postulaciones.
        Postulacion::query()->delete();
        $convocatoria->update(['fecha_publicacion' => now()->subDays(10), 'fecha_cierre' => now()->subDay()->toDateString()]);
        $this->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria))->assertSessionHasErrors('convocatoria');

        // Cerrada: la política ni siquiera la muestra al estudiante.
        $convocatoria->update(['estado' => Convocatoria::CERRADA]);
        $this->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria))->assertForbidden();
        $this->assertSame(0, Postulacion::count());
    }

    public function test_el_estudiante_solo_ve_convocatorias_publicadas_y_vigentes(): void
    {
        $empresa = Convocatoria::firstOrFail()->id_empresa;
        $usuario = (int) DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.empresa@portal.local')->value('id_usuario');
        $base = [
            'id_empresa' => $empresa, 'descripcion' => 'x', 'requisitos' => 'x', 'enlace_postulacion' => 'https://x.test',
            'tipo_practica' => 'preprofesional', 'modalidad' => 'remota', 'vacantes' => 1, 'registrada_por' => $usuario,
            'fecha_cierre' => now()->addDays(10)->toDateString(),
        ];
        Convocatoria::create($base + ['titulo' => 'Borrador secreto', 'estado' => 'borrador']);
        Convocatoria::create($base + ['titulo' => 'En revision secreta', 'estado' => 'en_revision']);

        $this->como('estudiante')->get(route('gestion-practicas.estudiante.convocatorias.index'))
            ->assertOk()->assertSee('Practicante de Desarrollo Web')
            ->assertDontSee('Borrador secreto')->assertDontSee('En revision secreta');

        $oculta = Convocatoria::where('titulo', 'Borrador secreto')->firstOrFail();
        $this->get(route('gestion-practicas.estudiante.convocatorias.show', $oculta))->assertForbidden();
    }

    public function test_solo_se_aprueba_u_observa_una_convocatoria_en_revision(): void
    {
        $convocatoria = Convocatoria::firstOrFail(); // ya publicada

        $this->como('admin')->post(route('gestion-practicas.revision.convocatorias.aprobar', $convocatoria))->assertSessionHasErrors('estado');
        $this->post(route('gestion-practicas.revision.convocatorias.observar', $convocatoria), ['descripcion' => 'x'])->assertSessionHasErrors('estado');
    }

    public function test_no_se_aprueba_una_convocatoria_con_la_fecha_de_cierre_vencida(): void
    {
        $convocatoria = Convocatoria::firstOrFail();
        $convocatoria->update(['estado' => Convocatoria::EN_REVISION, 'fecha_publicacion' => null, 'publicada_por' => null,
            'fecha_cierre' => now()->subDay()->toDateString()]);

        $this->como('admin')->post(route('gestion-practicas.revision.convocatorias.aprobar', $convocatoria))->assertSessionHasErrors('estado');
        $this->assertSame(Convocatoria::EN_REVISION, $convocatoria->refresh()->estado);
    }

    public function test_solo_quien_fue_seleccionado_registra_la_practica(): void
    {
        $convocatoria = Convocatoria::firstOrFail();
        $this->como('estudiante')->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria));
        $postulacion = Postulacion::firstOrFail(); // sigue «postulado»

        $this->post(route('gestion-practicas.estudiante.practicas.store', $postulacion), [
            'area' => 'Software', 'funciones' => 'x', 'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonth()->toDateString(), 'horas_totales' => 100,
            'carta' => UploadedFile::fake()->create('carta.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('postulacion');
        $this->assertSame(0, Practica::count());
    }

    public function test_la_practica_exige_la_carta_y_fechas_coherentes(): void
    {
        $convocatoria = Convocatoria::firstOrFail();
        $this->como('estudiante')->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria));
        $postulacion = Postulacion::firstOrFail();
        $this->post(route('gestion-practicas.estudiante.postulaciones.resultado', $postulacion), ['resultado' => 'seleccionado']);

        $this->post(route('gestion-practicas.estudiante.practicas.store', $postulacion), [
            'area' => 'Software', 'funciones' => 'x', 'fecha_inicio' => '2026-12-10', 'fecha_fin' => '2026-12-01', 'horas_totales' => 100,
        ])->assertSessionHasErrors(['carta', 'fecha_fin']);
        $this->assertSame(0, Practica::count());
    }

    public function test_no_se_envia_a_revision_sin_carta_de_aceptacion(): void
    {
        $practica = $this->practicaRegistrada();
        $practica->documentos()->delete();

        $this->como('estudiante')->post(route('gestion-practicas.estudiante.practicas.enviar', $practica))->assertSessionHasErrors('archivo');
        $this->assertSame(Practica::REGISTRADA, $practica->refresh()->estado);
    }

    public function test_los_informes_solo_se_suben_con_la_practica_en_curso(): void
    {
        $practica = $this->practicaRegistrada();

        $this->como('estudiante')->post(route('gestion-practicas.documentos.store', $practica), [
            'tipo_documento' => 'informe_final', 'archivo' => UploadedFile::fake()->create('i.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('archivo');
    }

    public function test_reemplazar_la_carta_deja_la_anterior_como_reemplazada(): void
    {
        $practica = $this->practicaRegistrada();

        $this->como('estudiante')->post(route('gestion-practicas.documentos.store', $practica), [
            'tipo_documento' => 'carta_aceptacion', 'archivo' => UploadedFile::fake()->create('nueva.pdf', 10, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, $practica->documentos()->where('estado', DocumentoPractica::REEMPLAZADO)->count());
        $this->assertSame(1, $practica->documentos()->where('estado', DocumentoPractica::PENDIENTE)->count());
    }

    public function test_el_administrador_no_valida_con_un_docente_inexistente(): void
    {
        $practica = $this->practicaRegistrada();
        $practica->update(['estado' => Practica::EN_REVISION]);

        $this->como('admin')->post(route('gestion-practicas.revision.practicas.validar', $practica), ['id_docente' => 999999])
            ->assertSessionHasErrors('id_docente');
        $this->assertSame(Practica::EN_REVISION, $practica->refresh()->estado);
    }

    public function test_no_se_cierra_sin_evaluacion_ni_informe_final_aprobado(): void
    {
        $practica = $this->practicaEnCurso();

        $this->como('supervisor')->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica))->assertSessionHasErrors('estado');

        $this->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), [
            'notas' => $this->notas(15),
        ])->assertSessionHasNoErrors();

        // Con nota pero sin informe final aprobado, tampoco cierra.
        $this->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica))->assertSessionHasErrors('estado');
        $this->assertSame(Practica::EN_CURSO, $practica->refresh()->estado);
    }

    public function test_la_evaluacion_exige_todos_los_criterios_y_notas_validas(): void
    {
        $practica = $this->practicaEnCurso();
        $ids = CriterioEvaluacion::pluck('id_criterio');

        $this->como('supervisor')->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), [
            'notas' => [$ids->first() => 15],
        ])->assertSessionHasErrors();

        $this->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), [
            'notas' => $ids->mapWithKeys(fn ($id) => [$id => 25])->all(),
        ])->assertSessionHasErrors();
        $this->assertSame(0, DB::table('gestion_practicas.evaluacion')->count());
    }

    public function test_la_nota_es_el_promedio_ponderado_y_define_aprobado_o_desaprobado(): void
    {
        $practica = $this->practicaEnCurso();
        $this->aprobarInforme($practica);

        $ids = CriterioEvaluacion::orderBy('id_criterio')->pluck('id_criterio')->all();
        // Pesos 25, 25, 20, 15, 15 con notas 20, 10, 10, 10, 10 → (500 + 250 + 200 + 150 + 150) / 100 = 12.50
        $notas = array_combine($ids, [20, 10, 10, 10, 10]);

        $this->como('supervisor')->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), ['notas' => $notas])
            ->assertSessionHasNoErrors();
        $this->assertEquals(12.5, app(EvaluacionService::class)->notaFinal($practica));

        $this->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica))->assertSessionHasNoErrors();
        $practica->refresh();
        $this->assertEquals(12.5, $practica->nota_final);
        $this->assertSame('aprobado', $practica->resultado);
    }

    public function test_una_nota_baja_desaprueba(): void
    {
        $practica = $this->practicaEnCurso();
        $this->aprobarInforme($practica);

        $this->como('supervisor')->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), ['notas' => $this->notas(8)]);
        $this->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica))->assertSessionHasNoErrors();

        $this->assertSame('desaprobado', $practica->refresh()->resultado);
    }

    public function test_una_practica_cerrada_ya_no_admite_cambios(): void
    {
        $practica = $this->practicaEnCurso();
        $this->aprobarInforme($practica);
        $this->como('supervisor')->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), ['notas' => $this->notas(14)]);
        $this->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica));

        $this->post(route('gestion-practicas.supervisor.practicas.seguimiento', $practica), [
            'fecha' => now()->toDateString(), 'tipo' => 'reunion', 'descripcion' => 'Tarde.',
        ])->assertSessionHasErrors('estado');
        $this->post(route('gestion-practicas.supervisor.practicas.evaluar', $practica), ['notas' => $this->notas(20)])->assertSessionHasErrors('estado');
        $this->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica))->assertSessionHasErrors('estado');
    }

    public function test_los_servicios_rechazan_transiciones_invalidas(): void
    {
        $this->como('admin');
        $admin = Actor::actual();
        $practica = $this->practicaRegistrada();

        $this->expectException(ValidationException::class);
        app(PracticaService::class)->validar($practica, $admin, $this->idDocente()); // aún no está en revisión
    }

    public function test_una_empresa_inactiva_no_crea_convocatorias(): void
    {
        $this->como('admin')->post(route('gestion-practicas.empresas.estado', Convocatoria::firstOrFail()->id_empresa))->assertSessionHasNoErrors();

        $this->como('empresa')->post(route('gestion-practicas.empresa.convocatorias.store'), [
            'titulo' => 'x', 'descripcion' => 'x', 'requisitos' => 'x', 'enlace_postulacion' => 'https://x.test',
            'tipo_practica' => 'preprofesional', 'modalidad' => 'remota', 'vacantes' => 1,
            'fecha_cierre' => now()->addDays(5)->toDateString(),
        ])->assertForbidden(); // la cuenta de una empresa inactiva deja de estar vinculada
    }

    public function test_no_se_registra_una_empresa_con_ruc_o_correo_repetidos(): void
    {
        $datos = [
            'ruc' => '20123456789', 'razon_social' => 'Duplicada', 'contacto_dni' => '71111111',
            'contacto_nombres' => 'Ana', 'contacto_apellido_paterno' => 'Paz',
            'correo_acceso' => 'nueva@duplicada.test', 'password' => 'Segura2026!',
        ];

        $this->como('admin')->post(route('gestion-practicas.empresas.store'), $datos)->assertSessionHasErrors('ruc');
        $this->post(route('gestion-practicas.empresas.store'), [...$datos, 'ruc' => '20111111111', 'correo_acceso' => 'practicas.empresa@portal.local'])
            ->assertSessionHasErrors('correo_acceso');
        $this->assertSame(1, User::where('email', 'practicas.empresa@portal.local')->count());
    }

    /** @return array<int, int|float> */
    private function notas(float $nota): array
    {
        return CriterioEvaluacion::pluck('id_criterio')->mapWithKeys(fn ($id) => [$id => $nota])->all();
    }

    private function aprobarInforme(Practica $practica): void
    {
        $this->como('estudiante')->post(route('gestion-practicas.documentos.store', $practica), [
            'tipo_documento' => 'informe_final', 'archivo' => UploadedFile::fake()->create('informe.pdf', 50, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $informe = $practica->documentos()->where('tipo_documento', 'informe_final')->firstOrFail();
        $this->como('supervisor')->post(route('gestion-practicas.documentos.revisar', $informe), ['estado' => 'aprobado'])->assertSessionHasNoErrors();
    }

    /** Práctica registrada (con carta) a nombre del estudiante de demostración, armada directamente. */
    private function practicaRegistrada(): Practica
    {
        $estudiante = (int) DB::table('core.estudiante')->where('codigo_universitario', 'E2026DEMO')->value('id_estudiante');
        $convocatoria = Convocatoria::firstOrFail();
        $admin = (int) DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.admin@portal.local')->value('id_usuario');

        $postulacion = Postulacion::create([
            'id_convocatoria' => $convocatoria->id_convocatoria, 'id_estudiante' => $estudiante, 'estado' => 'seleccionado',
            'resultado_en' => now(), 'resultado_registrado_por' => $admin,
        ])->refresh();

        $practica = Practica::create([
            'id_estudiante' => $estudiante, 'id_empresa' => $convocatoria->id_empresa, 'area' => 'Software',
            'funciones' => 'Desarrollo.', 'tipo_practica' => 'preprofesional', 'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(), 'horas_totales' => 240, 'estado' => 'registrada',
        ])->refresh();
        $postulacion->update(['id_practica' => $practica->id_practica]);

        $archivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => DB::table('seguridad.modulo_sistema')->where('codigo', 'gestion_practicas')->value('id_modulo'),
            'nombre_original' => 'carta.pdf', 'ruta_almacenamiento' => 'gestion-practicas/prueba/carta-'.$practica->id_practica.'.pdf',
            'tipo_mime' => 'application/pdf', 'tamano_bytes' => 9, 'subido_por' => $admin,
        ], 'id_archivo');
        $practica->documentos()->create(['id_archivo' => $archivo, 'tipo_documento' => 'carta_aceptacion']);

        return $practica;
    }

    /** Práctica en curso con el supervisor de demostración asignado. */
    private function practicaEnCurso(): Practica
    {
        $practica = $this->practicaRegistrada();
        $admin = (int) DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.admin@portal.local')->value('id_usuario');

        $practica->update([
            'estado' => Practica::EN_CURSO, 'validado_por' => $admin, 'validada_en' => now(),
            'id_docente_supervisor' => $this->idDocente(), 'supervisor_asignado_por' => $admin, 'supervisor_asignado_en' => now(),
        ]);

        return $practica;
    }
}
