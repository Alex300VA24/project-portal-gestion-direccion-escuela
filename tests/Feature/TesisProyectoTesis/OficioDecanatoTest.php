<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Solicitud de asesor: el Decanato no usa el sistema. Al registrar la solicitud se genera el oficio imprimible
 * (y su constancia en PDF guardada en la solicitud) que el Director envía por el canal oficial.
 */
final class OficioDecanatoTest extends TesisTestCase
{
    use EnviaFormularios;

    private Proyecto $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->irASemanaNueveDelPeriodo();
        $this->seed(EscenariosTesisSeeder::class);
        $this->p = Proyecto::query()->where('codigo', 'PT-DEMO-01')->firstOrFail();
    }

    public function test_registrar_la_solicitud_genera_el_oficio_y_deja_constancia(): void
    {
        $html = $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.asesor.solicitar', $this->p))->assertOk()
            ->assertSee('El sistema registra la solicitud; el envío al Decanato se hace por el canal oficial.')->getContent();

        // Sugiere al primer especialista que ofrece el formulario.
        $this->assertSame(1, preg_match('/name="docente_sugerido" value="(DOC-\d+)"/', $html, $m));
        $sugerido = \App\Modules\TesisProyectoTesis\Services\IdentidadService::class;
        $nombreSugerido = app($sugerido)->nombreDocente(app($sugerido)->idDocentePorCodigo($m[1]));
        $respuesta = $this->enviarFormulario($html, route('tesis-proyecto-tesis.director.asesor.enviar', $this->p), ['docente_sugerido' => $m[1]], 'Registrar solicitud y generar oficio')
            ->assertSessionHasNoErrors();
        $s = Solicitud::query()->where('id_proyecto', $this->p->id_proyecto)->latest('id_solicitud')->firstOrFail();
        $respuesta->assertRedirect(route('tesis-proyecto-tesis.director.oficio', $s));

        // Constancia: PDF guardado y enlazado a la solicitud.
        $this->assertNotNull($s->id_archivo);
        $ruta = DB::table('comun.archivo')->where('id_archivo', $s->id_archivo)->value('ruta_almacenamiento');
        $this->assertStringStartsWith('tesis/oficios/', $ruta);
        $this->assertStringStartsWith('%PDF-1.4', Storage::disk('local')->get($ruta));

        // Oficio imprimible con los datos pedidos.
        $numero = sprintf('%03d', Solicitud::query()->whereYear('presentada_en', $s->presentada_en->year)->where('id_tipo_solicitud', $s->id_tipo_solicitud)->where('id_solicitud', '<=', $s->id_solicitud)->count()).'-'.$s->presentada_en->year.'-EPII-D';
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.oficio', $s))->assertOk()
            ->assertSee('OFICIO N.° '.$numero)
            ->assertSee(['Decano', $s->asunto, 'PT-DEMO-01', $this->p->titulo, 'Kevin Aguilar Ríos', $nombreSugerido, 'Imprimir oficio', 'Constancia en PDF', 'Volver a Panel general'])
            ->assertSee('El sistema registra la solicitud; el envío al Decanato se hace por el canal oficial.');

        // La pantalla de solicitud indica que ya se registró y enlaza al oficio.
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.asesor.solicitar', $this->p))
            ->assertSee('se envía al Decanato por el canal oficial')->assertSee(route('tesis-proyecto-tesis.director.oficio', $s));

        // La Secretaría ve el oficio en PDF al registrar la resolución; el aviso no dice que el sistema lo envió.
        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.secretaria.resoluciones', ['solicitud' => $s->uuid]))
            ->assertSee(route('tesis-proyecto-tesis.archivos.descargar', ['oficio', $s->uuid]));
        $this->como('secretaria@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['oficio', $s->uuid]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $aviso = DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('secretaria@portal.local'))->where('tipo_evento', 'asesor.solicitado')->orderByDesc('id_notificacion')->value('mensaje');
        $this->assertStringContainsString('canal oficial', $aviso);
        $this->assertStringContainsString($numero, $aviso);
    }

    public function test_solo_el_director_ve_el_oficio(): void
    {
        $html = $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.asesor.solicitar', $this->p))->getContent();
        $this->enviarFormulario($html, route('tesis-proyecto-tesis.director.asesor.enviar', $this->p), [], 'Registrar solicitud y generar oficio')->assertSessionHasNoErrors();
        $s = Solicitud::query()->where('id_proyecto', $this->p->id_proyecto)->latest('id_solicitud')->firstOrFail();

        foreach (['secretaria@portal.local', 'asesor@portal.local', 'est01@portal.local'] as $correo) {
            $this->como($correo)->get(route('tesis-proyecto-tesis.director.oficio', $s))->assertForbidden();
        }
        $this->como('est02@portal.local')->get(route('tesis-proyecto-tesis.archivos.descargar', ['oficio', $s->uuid]))->assertForbidden();
        $this->como('director@portal.local')->get('/tesis-proyecto-tesis/director/solicitudes/'.$s->id_solicitud.'/oficio')->assertNotFound();
    }
}
