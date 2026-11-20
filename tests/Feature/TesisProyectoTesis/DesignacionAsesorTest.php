<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Models\AsesorProyecto;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Resolucion;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\NavegacionService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Support\Facades\DB;

final class DesignacionAsesorTest extends TesisTestCase
{
    private function solicitud(): array
    {
        return [
            'destinatario' => 'Decano de la Facultad',
            'destinatario_area' => 'Decanato',
            'asunto' => 'Solicitud de designación de asesor',
            'detalle' => 'Solicito la designación de un asesor especialista en la línea del proyecto.',
            'docente_sugerido' => 'DOC-101',
        ];
    }

    private function resolucion(array $extra = []): array
    {
        return array_replace([
            'numero' => '0451-2026-FCFYM',
            'fecha' => now()->toDateString(),
            'organo_emisor' => 'Decanato',
            'dirigido_a' => 'Director de Escuela',
            'docente' => 'DOC-101',
            'asunto' => 'Designación de asesor',
            'detalle' => 'Designar al docente como asesor.',
            'archivo' => $this->pdf('resolucion.pdf'),
        ], $extra);
    }

    private function solicitar(Proyecto $p): void
    {
        $this->como('director@portal.local')
            ->post(route('tesis-proyecto-tesis.director.asesor.enviar', $p), $this->solicitud())
            ->assertRedirect(route('tesis-proyecto-tesis.director.oficio', \App\Modules\TesisProyectoTesis\Models\Solicitud::query()->where('id_proyecto', $p->id_proyecto)->latest('id_solicitud')->firstOrFail()))
            ->assertSessionHasNoErrors();
    }

    public function test_flujo_solicitud_resolucion_asesor_activo_y_notificado(): void
    {
        $p = $this->proyecto(['alumno@portal.local', 'alumno2@portal.local']);

        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.panel'))
            ->assertOk()->assertSee('Requieren tu acción')->assertSee($p->codigo)->assertSee('Solicitar asesor');
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.asesor.solicitar', $p))
            ->assertOk()->assertSee('Mg. Carlos Medina Ruiz')->assertSee('DOC-101');

        $this->solicitar($p);
        $s = Solicitud::query()->where('id_proyecto', $p->id_proyecto)->firstOrFail();
        $this->assertSame('Pendiente', $s->estado);
        $this->assertSame($this->idDocente('asesor@portal.local'), (int) $s->id_docente_sugerido);
        $this->assertSame(Estados::REGISTRADO, $p->refresh()->estado);

        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.secretaria.resoluciones'))
            ->assertOk()->assertSee($p->codigo)->assertSee('En Decanato');

        $this->como('secretaria@portal.local')
            ->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $s), $this->resolucion())
            ->assertRedirect(route('tesis-proyecto-tesis.secretaria.resoluciones'))
            ->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame(Estados::CON_ASESOR, $p->estado);
        $this->assertSame('Atendida', $s->refresh()->estado);
        $asesor = AsesorProyecto::query()->where('id_proyecto', $p->id_proyecto)->where('estado', 'Activo')->firstOrFail();
        $this->assertSame($this->idDocente('asesor@portal.local'), (int) $asesor->id_docente);
        $this->assertNotNull($asesor->id_resolucion);
        $this->assertDatabaseHas('tesis.historial_estado', ['id_proyecto' => $p->id_proyecto, 'estado_anterior' => 'Registrado', 'estado_nuevo' => 'ConAsesor', 'cambiado_por' => $this->idUsuario('secretaria@portal.local')]);

        foreach (['asesor@portal.local', 'alumno@portal.local', 'alumno2@portal.local', 'director@portal.local'] as $correo) {
            $this->assertTrue(DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario($correo))->where('tipo_evento', 'asesor.designado')->exists(), "Sin aviso a $correo");
        }

        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertSee('Res. N.° 0451-2026-FCFYM', false);
        $asesor = $this->user('asesor@portal.local');
        $this->assertContains('asesor', app(NavegacionService::class)->contextos($asesor, app(IdentidadService::class)->actual($asesor)));
        $uuid = Resolucion::query()->firstOrFail()->uuid;
        $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['resolucion', $uuid]))->assertOk();
        $this->como('alumno3@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['resolucion', $uuid]))->assertForbidden();
    }

    public function test_no_se_duplica_la_solicitud_ni_se_solicita_fuera_de_estado(): void
    {
        $p = $this->proyecto();
        $this->solicitar($p);

        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.asesor.enviar', $p), $this->solicitud())
            ->assertSessionHasErrors('asunto');
        $this->assertSame(1, Solicitud::count());

        $otro = $this->proyecto(['alumno2@portal.local'], Estados::CON_ASESOR, asesor: 'asesor@portal.local');
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.asesor.enviar', $otro), $this->solicitud())
            ->assertSessionHasErrors('asunto');
    }

    public function test_docente_sugerido_debe_ser_especialista_de_la_linea(): void
    {
        $p = $this->proyecto(); // línea LI-SW: especialistas DOC-101, DOC-104, DOC-105

        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.asesor.enviar', $p), [...$this->solicitud(), 'docente_sugerido' => 'DOC-103'])
            ->assertSessionHasErrors('docente_sugerido');
        $this->como('director@portal.local')->post(route('tesis-proyecto-tesis.director.asesor.enviar', $p), [...$this->solicitud(), 'detalle' => ''])
            ->assertSessionHasErrors('detalle');
        $this->assertSame(0, Solicitud::count());
    }

    public function test_validaciones_de_la_resolucion(): void
    {
        $p = $this->proyecto();
        $this->solicitar($p);
        $s = Solicitud::query()->firstOrFail();

        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $s), $this->resolucion([
            'fecha' => now()->addDay()->toDateString(), 'docente' => 'NO-EXISTE', 'archivo' => null,
        ]))->assertSessionHasErrors(['fecha', 'archivo']);

        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $s), $this->resolucion(['docente' => 'NO-EXISTE']))
            ->assertSessionHasErrors('docente');

        $this->assertSame(0, Resolucion::count());
        $this->assertSame(Estados::REGISTRADO, $p->refresh()->estado);
    }

    public function test_numero_de_resolucion_unico_y_doble_envio_rechazado(): void
    {
        $p1 = $this->proyecto(['alumno@portal.local']);
        $p2 = $this->proyecto(['alumno2@portal.local']);
        $this->solicitar($p1);
        $this->solicitar($p2);
        [$s1, $s2] = Solicitud::query()->orderBy('id_solicitud')->get()->all();

        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $s1), $this->resolucion())->assertSessionHasNoErrors();
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $s1), $this->resolucion(['numero' => 'OTRA-1']))->assertSessionHasErrors('numero');
        $this->como('secretaria@portal.local')->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $s2), $this->resolucion())->assertSessionHasErrors('numero');

        $this->assertSame(1, Resolucion::count());
        $this->assertSame(1, AsesorProyecto::count());
    }

    public function test_autorizacion_por_rol(): void
    {
        $p = $this->proyecto();

        foreach (['alumno@portal.local', 'secretaria@portal.local', 'asesor@portal.local'] as $correo) {
            $this->como($correo)->get(route('tesis-proyecto-tesis.director.panel'))->assertForbidden();
            $this->como($correo)->post(route('tesis-proyecto-tesis.director.asesor.enviar', $p), $this->solicitud())->assertForbidden();
        }

        $this->solicitar($p);
        $s = Solicitud::query()->firstOrFail();
        foreach (['alumno@portal.local', 'director@portal.local', 'asesor@portal.local'] as $correo) {
            $this->como($correo)->get(route('tesis-proyecto-tesis.secretaria.resoluciones'))->assertForbidden();
            $this->como($correo)->post(route('tesis-proyecto-tesis.secretaria.resoluciones.registrar', $s), $this->resolucion())->assertForbidden();
        }

        $this->como('director@portal.local')->get('/tesis-proyecto-tesis/director/proyectos/'.$p->id_proyecto.'/solicitar-asesor')->assertNotFound();
        $this->assertSame(0, Resolucion::count());
    }

    public function test_listado_general_filtra_por_estado_y_valida_el_filtro(): void
    {
        $a = $this->proyecto(['alumno@portal.local']);
        $b = $this->proyecto(['alumno2@portal.local'], Estados::CON_ASESOR, asesor: 'asesor@portal.local');

        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.proyectos', ['estado' => 'ConAsesor']))
            ->assertOk()->assertSee($b->codigo)->assertDontSee($a->codigo);
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.proyectos', ['estado' => "x'; DROP"]))
            ->assertSessionHasErrors('estado');
    }
}
