<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use Illuminate\Support\Facades\DB;

/**
 * Responder observaciones con el documento corregido: el PDF se registra como nueva entrega y la observación
 * queda «corregida en» esa versión; el revisor ve el enlace y recibe un aviso.
 */
final class RespuestaConDocumentoTest extends TesisTestCase
{
    use EnviaFormularios;

    private Proyecto $p;

    protected function setUp(): void
    {
        parent::setUp();
        $this->irASemanaNueveDelPeriodo();
        $this->seed(EscenariosTesisSeeder::class);
        $this->p = Proyecto::query()->where('codigo', 'PT-DEMO-04')->firstOrFail();
    }

    /** @return list<Observacion> observaciones pendientes de PT-DEMO-04 */
    private function pendientes(): array
    {
        return Observacion::query()
            ->join('tesis.revision as r', 'r.id_revision', '=', 'tesis.observacion.id_revision')
            ->join('tesis.entrega as e', 'e.id_entrega', '=', 'r.id_entrega')
            ->where('e.id_proyecto', $this->p->id_proyecto)->where('tesis.observacion.estado', 'Pendiente')
            ->orderBy('tesis.observacion.id_observacion')->select('tesis.observacion.*')->get()->all();
    }

    private function formulario(): string
    {
        return $this->como('est04@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $this->p))->assertOk()->getContent();
    }

    public function test_responder_con_pdf_crea_la_entrega_y_avisa_al_revisor_con_enlace(): void
    {
        [$o1, $o2] = $this->pendientes();
        $maxAntes = (int) Entrega::query()->where('id_proyecto', $this->p->id_proyecto)->where('etapa', $this->p->etapa)->max('version');
        $avisosEntrega = DB::table('comun.notificacion')->where('tipo_evento', 'entrega.subida')->count();

        // 1) Primera observación con el PDF corregido.
        $html = $this->formulario();
        $this->assertStringContainsString('Documento corregido (PDF, opcional)', $html);
        $this->enviarFormulario($html, route('tesis-proyecto-tesis.alumno.observaciones.responder', $o1->uuid), [
            'respuesta' => 'Reformulé el objetivo específico 2 en la sección 1.4.',
            'archivo' => $this->pdf('corregido.pdf'),
        ], 'Enviar respuesta')->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Respuesta enviada con el documento corregido (registrado como v'.($maxAntes + 1).'). El revisor confirmará si la observación queda levantada.');

        $nueva = Entrega::query()->where('id_proyecto', $this->p->id_proyecto)->latest('id_entrega')->firstOrFail();
        $this->assertSame([$maxAntes + 1, 'Avance', $this->p->etapa, 'Pendiente'], [(int) $nueva->version, $nueva->tipo, $nueva->etapa, $nueva->estado]);
        $o1->refresh();
        $this->assertSame(['Respondida', $nueva->id_entrega], [$o1->estado, (int) $o1->id_entrega_correccion]);
        $this->assertSame($avisosEntrega, DB::table('comun.notificacion')->where('tipo_evento', 'entrega.subida')->count(), 'Un solo aviso: el de la respuesta.');
        $aviso = DB::table('comun.notificacion')->where('id_usuario_destino', $this->idUsuario('asesor@portal.local'))
            ->where('tipo_evento', 'observacion.respondida')->orderByDesc('id_notificacion')->firstOrFail();
        $this->assertStringContainsString("v{$nueva->version}", $aviso->mensaje);

        // 2) Segunda observación: elige la versión recién subida.
        $html = $this->formulario();
        $this->assertStringContainsString('value="'.$nueva->uuid.'"', $html, 'La nueva versión aparece en «Corregida en».');
        $this->enviarFormulario($html, route('tesis-proyecto-tesis.alumno.observaciones.responder', $o2->uuid), [
            'respuesta' => 'Definí las dimensiones de la variable en la matriz.',
            'entrega' => $nueva->uuid,
        ], 'Enviar respuesta')->assertSessionHasNoErrors()
            ->assertSessionHas('status', "Respuesta enviada: corregida en v{$nueva->version}. El revisor confirmará si la observación queda levantada.");
        $this->assertSame($nueva->id_entrega, (int) $o2->refresh()->id_entrega_correccion);

        // 3) El asesor ve el enlace al documento corregido antes de «Dar por levantada» y puede abrirlo.
        $enlace = route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $nueva->uuid]);
        $html = $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyecto', $this->p))->assertOk()->getContent();
        $this->assertStringContainsString('href="'.$enlace.'"', $html);
        $this->assertStringContainsString('Ver documento corregido (v'.$nueva->version.')', $html);
        $this->assertLessThan(strpos($html, 'Dar por levantada'), strpos($html, 'Ver documento corregido'));
        $this->como('asesor@portal.local')->get($enlace)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_sin_adjunto_ni_version_advierte_pero_permite_responder(): void
    {
        [$o1] = $this->pendientes();
        $html = $this->formulario();
        $this->assertStringContainsString('No adjuntaste el documento corregido ni elegiste una versión', $html, 'Advertencia en el formulario.');

        $this->enviarFormulario($html, route('tesis-proyecto-tesis.alumno.observaciones.responder', $o1->uuid), [
            'respuesta' => 'Lo corregí en el documento de esta semana.',
        ], 'Enviar respuesta')->assertSessionHasNoErrors()
            ->assertSessionHas('aviso', 'No adjuntaste el documento corregido ni elegiste una versión. Si ya corregiste el documento, súbelo o elige la versión en «Corregida en» para que el revisor pueda verlo.');

        $this->assertSame('Respondida', $o1->refresh()->estado);
        $this->assertNull($o1->id_entrega_correccion);
        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyecto', $this->p))->assertSee('sin versión corregida indicada');
    }

    public function test_el_adjunto_debe_ser_pdf(): void
    {
        [$o1] = $this->pendientes();
        $html = $this->formulario();

        $this->enviarFormulario($html, route('tesis-proyecto-tesis.alumno.observaciones.responder', $o1->uuid), [
            'respuesta' => 'Adjunto la corrección.',
            'archivo' => \Illuminate\Http\UploadedFile::fake()->create('corregido.docx', 50, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
        ], 'Enviar respuesta')->assertSessionHasErrors(['archivo' => 'El archivo «documento corregido (PDF)» debe ser un PDF.']);

        $this->assertSame('Pendiente', $o1->refresh()->estado);
    }
}
