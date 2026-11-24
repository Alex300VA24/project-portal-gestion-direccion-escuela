<?php

namespace Tests\Feature\GestionPracticas;

use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/** Un estudiante lleva una práctica a la vez: con una activa no postula, no se marca seleccionado ni registra otra. */
final class UnaPracticaActivaTest extends PracticasTestCase
{
    private function idEstudiante(): int
    {
        return (int) DB::table('core.estudiante')->where('codigo_universitario', 'E2026DEMO')->value('id_estudiante');
    }

    private function idUsuario(string $actor): int
    {
        return (int) DB::table('seguridad.usuario')->where('correo_institucional', "practicas.{$actor}@portal.local")->value('id_usuario');
    }

    /** Práctica del estudiante de demostración en el estado indicado, con la trazabilidad que exige la base de datos. */
    private function crearPractica(string $estado): Practica
    {
        $admin = $this->idUsuario('admin');
        $datos = [
            'id_estudiante' => $this->idEstudiante(), 'id_empresa' => Convocatoria::firstOrFail()->id_empresa, 'area' => 'Software',
            'funciones' => 'Desarrollo.', 'tipo_practica' => 'preprofesional', 'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(), 'horas_totales' => 240, 'estado' => $estado,
        ];

        if (in_array($estado, [Practica::EN_CURSO, Practica::FINALIZADA], true)) {
            $datos += [
                'validado_por' => $admin, 'validada_en' => now(), 'id_docente_supervisor' => $this->idDocente(),
                'supervisor_asignado_por' => $admin, 'supervisor_asignado_en' => now(),
            ];
        }

        if ($estado === Practica::FINALIZADA) {
            $datos += ['nota_final' => 16, 'resultado' => 'aprobado', 'cerrada_por' => $admin, 'cerrada_en' => now()];
        }

        return Practica::create($datos)->refresh();
    }

    private function crearPostulacion(string $estado): Postulacion
    {
        return Postulacion::create([
            'id_convocatoria' => Convocatoria::firstOrFail()->id_convocatoria, 'id_estudiante' => $this->idEstudiante(),
            'estado' => $estado, 'resultado_en' => $estado === Postulacion::POSTULADO ? null : now(),
            'resultado_registrado_por' => $estado === Postulacion::POSTULADO ? null : $this->idUsuario('estudiante'),
        ])->refresh();
    }

    public function test_no_postula_mientras_tiene_una_practica_activa(): void
    {
        foreach ([Practica::REGISTRADA, Practica::EN_REVISION, Practica::OBSERVADA, Practica::EN_CURSO] as $estado) {
            $practica = $this->crearPractica($estado);

            $this->como('estudiante')->post(route('gestion-practicas.estudiante.convocatorias.postular', Convocatoria::firstOrFail()))
                ->assertSessionHasErrors('convocatoria');

            $this->assertSame(0, Postulacion::count(), "Postuló con una práctica «{$estado}»");
            $practica->delete();
        }
    }

    public function test_el_mensaje_dice_donde_esta_la_practica_activa_y_cuando_podra_postular(): void
    {
        $this->crearPractica(Practica::EN_CURSO);

        $respuesta = $this->como('estudiante')->post(route('gestion-practicas.estudiante.convocatorias.postular', Convocatoria::firstOrFail()));

        $mensaje = $respuesta->baseResponse->getSession()->get('errors')['default']['messages']['convocatoria'][0];
        $this->assertStringContainsString('práctica activa', $mensaje);
        $this->assertStringContainsString('TechNova', $mensaje);
        $this->assertStringContainsString('cuando la finalice', $mensaje);
    }

    public function test_vuelve_a_postular_cuando_la_practica_finalizo(): void
    {
        $this->crearPractica(Practica::FINALIZADA);

        $this->como('estudiante')->post(route('gestion-practicas.estudiante.convocatorias.postular', Convocatoria::firstOrFail()))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Postulacion::count());
    }

    public function test_no_se_marca_como_seleccionado_con_una_practica_activa(): void
    {
        $postulacion = $this->crearPostulacion(Postulacion::POSTULADO);
        $this->crearPractica(Practica::EN_CURSO);

        $this->como('estudiante')->post(route('gestion-practicas.estudiante.postulaciones.resultado', $postulacion), ['resultado' => 'seleccionado'])
            ->assertSessionHasErrors('resultado');
        $this->assertSame(Postulacion::POSTULADO, $postulacion->refresh()->estado);

        // Cerrar la postulación sin ser seleccionado sigue permitido.
        $this->post(route('gestion-practicas.estudiante.postulaciones.resultado', $postulacion), ['resultado' => 'no_seleccionado'])
            ->assertSessionHasNoErrors();
        $this->assertSame(Postulacion::NO_SELECCIONADO, $postulacion->refresh()->estado);
    }

    public function test_no_registra_una_segunda_practica_con_una_activa(): void
    {
        $postulacion = $this->crearPostulacion(Postulacion::SELECCIONADO);
        $this->crearPractica(Practica::EN_REVISION);

        $this->como('estudiante')->post(route('gestion-practicas.estudiante.practicas.store', $postulacion), [
            'area' => 'Redes', 'funciones' => 'Soporte.', 'fecha_inicio' => now()->addDay()->toDateString(),
            'fecha_fin' => now()->addMonths(2)->toDateString(), 'horas_totales' => 120,
            'carta' => UploadedFile::fake()->create('carta.pdf', 50, 'application/pdf'),
        ])->assertSessionHasErrors('postulacion');

        $this->assertSame(1, Practica::count());
    }

    public function test_activa_de_ignora_las_practicas_finalizadas_y_las_de_otros_estudiantes(): void
    {
        $this->crearPractica(Practica::FINALIZADA);
        $this->assertNull(Practica::activaDe($this->idEstudiante()));
        $this->assertNull(Practica::activaDe(null));

        $this->crearPractica(Practica::EN_CURSO);
        $this->assertNotNull(Practica::activaDe($this->idEstudiante()));
        $this->assertNull(Practica::activaDe($this->idEstudiante() + 999));
    }
}
