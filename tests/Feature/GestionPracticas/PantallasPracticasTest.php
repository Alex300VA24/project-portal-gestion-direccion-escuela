<?php

namespace Tests\Feature\GestionPracticas;

use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use Illuminate\Support\Facades\DB;

/** Humo de interfaz: todas las pantallas del módulo se renderizan para el rol que corresponde. */
final class PantallasPracticasTest extends PracticasTestCase
{
    public function test_todas_las_pantallas_se_renderizan(): void
    {
        $convocatoria = Convocatoria::firstOrFail();
        $practica = $this->practica($convocatoria);
        $pendiente = $this->postulacionSeleccionadaSinPractica($convocatoria);

        $this->como('admin');
        foreach ([
            route('gestion-practicas.empresas.index'),
            route('gestion-practicas.empresas.create'),
            route('gestion-practicas.revision.convocatorias.index'),
            route('gestion-practicas.revision.convocatorias.index', ['estado' => 'todas']),
            route('gestion-practicas.revision.convocatorias.show', $convocatoria),
            route('gestion-practicas.revision.practicas.index', ['estado' => 'todas']),
            route('gestion-practicas.revision.practicas.show', $practica),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->como('empresa');
        foreach ([
            route('gestion-practicas.empresa.perfil.edit'),
            route('gestion-practicas.empresa.convocatorias.index'),
            route('gestion-practicas.empresa.convocatorias.create'),
            route('gestion-practicas.empresa.convocatorias.show', $convocatoria),
            route('gestion-practicas.empresa.convocatorias.edit', $convocatoria),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        $this->como('estudiante');
        foreach ([
            route('gestion-practicas.estudiante.convocatorias.index'),
            route('gestion-practicas.estudiante.convocatorias.show', $convocatoria),
            route('gestion-practicas.estudiante.postulaciones.index'),
            route('gestion-practicas.estudiante.practicas.index'),
            route('gestion-practicas.estudiante.practicas.show', $practica),
            route('gestion-practicas.estudiante.practicas.edit', $practica),
        ] as $url) {
            $this->get($url)->assertOk();
        }

        // Con una práctica activa no se abre el registro de otra: vuelve a Mis postulaciones con el motivo.
        $this->get(route('gestion-practicas.estudiante.practicas.create', $pendiente))
            ->assertRedirect(route('gestion-practicas.estudiante.postulaciones.index'))
            ->assertSessionHasErrors('postulacion');

        $this->como('supervisor');
        $practica->update([
            'estado' => Practica::EN_CURSO, 'validada_en' => now(),
            'validado_por' => DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.admin@portal.local')->value('id_usuario'),
        ]);
        foreach ([
            route('gestion-practicas.supervisor.practicas.index'),
            route('gestion-practicas.supervisor.practicas.show', $practica),
        ] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_el_perfil_de_la_empresa_se_actualiza(): void
    {
        $this->como('empresa')->put(route('gestion-practicas.empresa.perfil.update'), [
            'nombre_comercial' => 'TechNova', 'direccion' => 'Av. Larco 500', 'telefono' => '044999999',
            'correo' => 'nuevo@technova.test', 'pagina_web' => 'https://technova.test',
        ])->assertSessionHasNoErrors();

        $empresa = DB::table('comun.empresa')->where('ruc', '20123456789')->first();
        $this->assertSame('Av. Larco 500', $empresa->direccion);
        $this->assertSame('nuevo@technova.test', $empresa->correo);
        $this->assertSame('20123456789', $empresa->ruc);
    }

    public function test_el_formulario_de_registro_se_muestra_si_no_hay_practica_activa(): void
    {
        $pendiente = $this->postulacionSeleccionadaSinPractica(Convocatoria::firstOrFail());

        $this->como('estudiante')->get(route('gestion-practicas.estudiante.practicas.create', $pendiente))
            ->assertOk()->assertSee('Registrar mi práctica')->assertSee('Carta de aceptación');
    }

    public function test_las_pantallas_de_detalle_ofrecen_volver_al_listado(): void
    {
        $convocatoria = Convocatoria::firstOrFail();
        $practica = $this->practica($convocatoria);
        $practica->update([
            'estado' => Practica::EN_CURSO, 'validada_en' => now(),
            'validado_por' => DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.admin@portal.local')->value('id_usuario'),
        ]);

        $this->como('admin')->get(route('gestion-practicas.revision.convocatorias.show', $convocatoria))->assertSee('Volver a revisar convocatorias');
        $this->get(route('gestion-practicas.revision.practicas.show', $practica))->assertSee('Volver a validar prácticas');

        $this->como('empresa')->get(route('gestion-practicas.empresa.convocatorias.show', $convocatoria))->assertSee('Volver a mis convocatorias');

        $this->como('estudiante')->get(route('gestion-practicas.estudiante.convocatorias.show', $convocatoria))->assertSee('Volver a convocatorias');
        $this->get(route('gestion-practicas.estudiante.practicas.show', $practica))
            ->assertSee('Volver a mis prácticas')
            ->assertSee(route('gestion-practicas.estudiante.practicas.index'), false);

        $this->como('supervisor')->get(route('gestion-practicas.supervisor.practicas.show', $practica))->assertSee('Volver a prácticas asignadas');
    }

    public function test_los_nombres_de_los_docentes_se_muestran_sin_espacios_de_mas(): void
    {
        $practica = $this->practica(Convocatoria::firstOrFail());
        $practica->update(['estado' => Practica::EN_REVISION, 'id_docente_supervisor' => null, 'supervisor_asignado_por' => null, 'supervisor_asignado_en' => null]);

        $this->como('admin')->get(route('gestion-practicas.revision.practicas.show', $practica))
            ->assertOk()->assertDontSee(' , ', false);
    }

    private function practica(Convocatoria $convocatoria): Practica
    {
        $estudiante = (int) DB::table('core.estudiante')->where('codigo_universitario', 'E2026DEMO')->value('id_estudiante');
        $admin = (int) DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.admin@portal.local')->value('id_usuario');

        $postulacion = Postulacion::create([
            'id_convocatoria' => $convocatoria->id_convocatoria, 'id_estudiante' => $estudiante, 'estado' => 'seleccionado',
            'resultado_en' => now(), 'resultado_registrado_por' => $admin,
        ])->refresh();

        $practica = Practica::create([
            'id_estudiante' => $estudiante, 'id_empresa' => $convocatoria->id_empresa, 'area' => 'Software',
            'funciones' => 'Desarrollo.', 'tipo_practica' => 'preprofesional', 'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(), 'horas_totales' => 240, 'estado' => 'registrada',
            'id_docente_supervisor' => $this->idDocente(), 'supervisor_asignado_por' => $admin, 'supervisor_asignado_en' => now(),
        ])->refresh();
        $postulacion->update(['id_practica' => $practica->id_practica]);

        return $practica;
    }

    private function postulacionSeleccionadaSinPractica(Convocatoria $base): Postulacion
    {
        $estudiante = (int) DB::table('core.estudiante')->where('codigo_universitario', 'E2026DEMO')->value('id_estudiante');
        $admin = (int) DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.admin@portal.local')->value('id_usuario');
        $otra = Convocatoria::create([
            'id_empresa' => $base->id_empresa, 'titulo' => 'Otra convocatoria', 'descripcion' => 'x', 'requisitos' => 'x',
            'enlace_postulacion' => 'https://x.test', 'tipo_practica' => 'preprofesional', 'modalidad' => 'remota', 'vacantes' => 1,
            'registrada_por' => $admin, 'estado' => 'borrador', 'fecha_cierre' => now()->addDays(10)->toDateString(),
        ])->refresh();

        return Postulacion::create([
            'id_convocatoria' => $otra->id_convocatoria, 'id_estudiante' => $estudiante, 'estado' => 'seleccionado',
            'resultado_en' => now(), 'resultado_registrado_por' => $admin,
        ])->refresh();
    }
}
