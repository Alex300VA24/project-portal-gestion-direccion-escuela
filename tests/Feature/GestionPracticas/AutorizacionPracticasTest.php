<?php

namespace Tests\Feature\GestionPracticas;

use App\Models\User;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Support\Acceso;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Cada actor solo accede a lo suyo. */
final class AutorizacionPracticasTest extends PracticasTestCase
{
    public function test_cada_rol_solo_entra_a_sus_pantallas(): void
    {
        $this->como('estudiante')->get(route('gestion-practicas.empresa.convocatorias.index'))->assertForbidden();
        $this->get(route('gestion-practicas.empresas.index'))->assertForbidden();
        $this->get(route('gestion-practicas.supervisor.practicas.index'))->assertForbidden();

        $this->como('empresa')->get(route('gestion-practicas.estudiante.convocatorias.index'))->assertForbidden();
        $this->get(route('gestion-practicas.revision.convocatorias.index'))->assertForbidden();

        $this->como('supervisor')->get(route('gestion-practicas.revision.practicas.index'))->assertForbidden();
        $this->como('admin')->get(route('gestion-practicas.supervisor.practicas.index'))->assertForbidden();
    }

    public function test_cada_rol_ve_su_panel(): void
    {
        foreach (['admin', 'empresa', 'estudiante', 'supervisor'] as $actor) {
            $this->como($actor)->get(route('gestion-practicas.panel'))->assertOk();
        }
    }

    public function test_una_empresa_no_ve_ni_edita_las_convocatorias_de_otra(): void
    {
        $ajena = Convocatoria::firstOrFail(); // pertenece a la empresa de demostración

        $this->como('admin')->post(route('gestion-practicas.empresas.store'), [
            'ruc' => '20555444333', 'razon_social' => 'Otra Empresa S.A.', 'contacto_dni' => '71000001',
            'contacto_nombres' => 'Ana', 'contacto_apellido_paterno' => 'Paz',
            'correo_acceso' => 'otra@empresa.test', 'password' => 'Segura2026!',
        ])->assertSessionHasNoErrors();

        $this->actingAs(User::where('email', 'otra@empresa.test')->firstOrFail())
            ->get(route('gestion-practicas.empresa.convocatorias.show', $ajena))->assertForbidden();
        $this->post(route('gestion-practicas.empresa.convocatorias.cerrar', $ajena))->assertForbidden();
        $this->assertSame(Convocatoria::PUBLICADA, $ajena->refresh()->estado);
    }

    public function test_un_estudiante_no_ve_la_practica_de_otro(): void
    {
        $practica = $this->practicaEnCurso();

        $this->actingAs($this->otroEstudiante())
            ->get(route('gestion-practicas.estudiante.practicas.show', $practica))->assertForbidden();
        $this->get(route('gestion-practicas.estudiante.practicas.edit', $practica))->assertForbidden();
        $this->post(route('gestion-practicas.estudiante.practicas.enviar', $practica))->assertForbidden();

        $this->como('estudiante')->get(route('gestion-practicas.estudiante.practicas.show', $practica))->assertOk();
    }

    public function test_un_supervisor_solo_opera_sobre_sus_practicas_asignadas(): void
    {
        $practica = $this->practicaEnCurso();

        $this->actingAs($this->otroSupervisor())
            ->get(route('gestion-practicas.supervisor.practicas.show', $practica))->assertForbidden();
        $this->post(route('gestion-practicas.supervisor.practicas.cerrar', $practica))->assertForbidden();
        $this->post(route('gestion-practicas.supervisor.practicas.seguimiento', $practica), [
            'fecha' => now()->toDateString(), 'tipo' => 'llamada', 'descripcion' => 'Intento ajeno.',
        ])->assertForbidden();

        $this->como('supervisor')->get(route('gestion-practicas.supervisor.practicas.show', $practica))->assertOk();
    }

    public function test_un_estudiante_no_gestiona_la_postulacion_de_otro(): void
    {
        $convocatoria = Convocatoria::firstOrFail();
        $this->como('estudiante')->post(route('gestion-practicas.estudiante.convocatorias.postular', $convocatoria));
        $postulacion = Postulacion::firstOrFail();

        $this->actingAs($this->otroEstudiante())
            ->post(route('gestion-practicas.estudiante.postulaciones.resultado', $postulacion), ['resultado' => 'seleccionado'])
            ->assertForbidden();
    }

    public function test_los_documentos_solo_los_descargan_quienes_ven_la_practica(): void
    {
        $practica = $this->practicaEnCurso();
        $documento = $practica->documentos()->firstOrFail();

        $this->como('estudiante')->get(route('gestion-practicas.documentos.descargar', $documento))->assertOk();
        $this->como('supervisor')->get(route('gestion-practicas.documentos.descargar', $documento))->assertOk();
        $this->como('admin')->get(route('gestion-practicas.documentos.descargar', $documento))->assertOk();
        $this->actingAs($this->otroEstudiante())->get(route('gestion-practicas.documentos.descargar', $documento))->assertForbidden();
    }

    public function test_descargar_un_documento_cuyo_archivo_ya_no_existe_responde_404(): void
    {
        $practica = $this->practicaEnCurso();
        $documento = $practica->documentos()->firstOrFail();

        Storage::disk('local')->delete('gestion-practicas/prueba/carta.pdf');

        $this->como('estudiante')->get(route('gestion-practicas.documentos.descargar', $documento))->assertNotFound();
    }

    public function test_una_cuenta_sin_vinculo_institucional_no_puede_operar(): void
    {
        $huerfano = User::create(['name' => 'Sin vínculo', 'email' => 'huerfano@portal.local', 'password' => 'Portal2026!']);
        $huerfano->forceFill(['email_verified_at' => now()])->save();
        $huerfano->assignRole(Acceso::ESTUDIANTE);

        $this->actingAs($huerfano)->get(route('gestion-practicas.panel'))->assertOk()->assertSee('no está vinculada');
        $this->post(route('gestion-practicas.estudiante.convocatorias.postular', Convocatoria::firstOrFail()))->assertForbidden();
    }

    /** Práctica ya validada y en curso con su carta de aceptación (se arma por servicios, no por HTTP). */
    private function practicaEnCurso(): Practica
    {
        $estudiante = (int) DB::table('core.estudiante')->where('codigo_universitario', 'E2026DEMO')->value('id_estudiante');
        $convocatoria = Convocatoria::firstOrFail();
        $admin = (int) DB::table('seguridad.usuario')->where('correo_institucional', 'practicas.admin@portal.local')->value('id_usuario');
        $docente = $this->idDocente();

        $postulacion = Postulacion::create([
            'id_convocatoria' => $convocatoria->id_convocatoria, 'id_estudiante' => $estudiante, 'estado' => 'seleccionado',
            'resultado_en' => now(), 'resultado_registrado_por' => $admin,
        ])->refresh();

        $practica = Practica::create([
            'id_estudiante' => $estudiante, 'id_empresa' => $convocatoria->id_empresa, 'area' => 'Software',
            'funciones' => 'Desarrollo.', 'tipo_practica' => 'preprofesional', 'fecha_inicio' => now()->toDateString(),
            'fecha_fin' => now()->addMonths(3)->toDateString(), 'horas_totales' => 240, 'estado' => 'en_curso',
            'validado_por' => $admin, 'validada_en' => now(), 'id_docente_supervisor' => $docente,
            'supervisor_asignado_por' => $admin, 'supervisor_asignado_en' => now(),
        ])->refresh();
        $postulacion->update(['id_practica' => $practica->id_practica]);

        Storage::disk('local')->put('gestion-practicas/prueba/carta.pdf', 'contenido');
        $archivo = DB::table('comun.archivo')->insertGetId([
            'id_modulo' => DB::table('seguridad.modulo_sistema')->where('codigo', 'gestion_practicas')->value('id_modulo'),
            'nombre_original' => 'carta.pdf', 'ruta_almacenamiento' => 'gestion-practicas/prueba/carta.pdf',
            'tipo_mime' => 'application/pdf', 'tamano_bytes' => 9, 'subido_por' => $admin,
        ], 'id_archivo');
        $practica->documentos()->create(['id_archivo' => $archivo, 'tipo_documento' => 'carta_aceptacion']);

        return $practica;
    }
}
