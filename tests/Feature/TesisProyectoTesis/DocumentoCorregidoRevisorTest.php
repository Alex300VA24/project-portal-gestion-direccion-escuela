<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Observacion;
use App\Modules\TesisProyectoTesis\Models\Proyecto;

/**
 * El documento corregido que adjunta el estudiante al responder se ve en todas las listas del revisor
 * (también en las pantallas de verificación del asesor y del jurado) y solo lo descargan quienes tienen
 * relación con el proyecto.
 */
final class DocumentoCorregidoRevisorTest extends TesisTestCase
{
    use EnviaFormularios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->irASemanaNueveDelPeriodo();
        $this->seed(EscenariosTesisSeeder::class);
    }

    private function demo(string $n): Proyecto
    {
        return Proyecto::query()->where('codigo', "PT-DEMO-{$n}")->firstOrFail();
    }

    /** Observaciones abiertas que registró un docente en el proyecto. */
    private function abiertasDe(Proyecto $p, string $docente): array
    {
        return Observacion::query()
            ->join('tesis.revision as r', 'r.id_revision', '=', 'tesis.observacion.id_revision')
            ->join('tesis.entrega as e', 'e.id_entrega', '=', 'r.id_entrega')
            ->where('e.id_proyecto', $p->id_proyecto)->where('r.id_docente', $this->idDocente($docente))
            ->whereIn('tesis.observacion.estado', ['Pendiente', 'Respondida'])
            ->orderBy('tesis.observacion.id_observacion')->select('tesis.observacion.*')->get()->all();
    }

    /** El estudiante responde con el PDF adjunto desde su formulario; devuelve la entrega creada. */
    private function responderConPdf(Proyecto $p, string $alumno, Observacion $o): Entrega
    {
        $html = $this->como($alumno)->get(route('tesis-proyecto-tesis.alumno.proyecto.observaciones', $p))->assertOk()->getContent();
        $this->enviarFormulario($html, route('tesis-proyecto-tesis.alumno.observaciones.responder', $o->uuid), [
            'respuesta' => 'Corregí lo observado; adjunto el documento.',
            'archivo' => $this->pdf('corregido.pdf'),
        ], 'Enviar respuesta')->assertSessionHasNoErrors();

        $o->refresh();
        $this->assertSame('Respondida', $o->estado);

        return Entrega::query()->findOrFail($o->id_entrega_correccion);
    }

    private function assertEnlaceAntesDeLevantar(string $html, Entrega $e): void
    {
        $enlace = route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $e->uuid]);
        $this->assertStringContainsString('corregida en v'.$e->version, $html);
        $this->assertStringContainsString('href="'.$enlace.'"', $html);
        $this->assertStringContainsString('Ver documento corregido (v'.$e->version.')', $html);
        $this->assertLessThan(strrpos($html, 'Dar por levantada'), strpos($html, 'Ver documento corregido'));
    }

    public function test_asesor_ve_y_descarga_el_documento_corregido_en_verificar_y_revisar(): void
    {
        $p = $this->demo('04');
        [$o1, $o2] = $this->abiertasDe($p, 'asesor@portal.local');
        $nueva = $this->responderConPdf($p, 'est04@portal.local', $o1);

        // Una respuesta sin adjunto ni versión muestra la advertencia.
        $this->como('est04@portal.local')->post(route('tesis-proyecto-tesis.alumno.observaciones.responder', $o2->uuid), ['respuesta' => 'Lo corregí en el documento.'])->assertSessionHasNoErrors();

        foreach (['asesor.verificar', 'asesor.revisar', 'asesor.proyecto'] as $ruta) {
            $params = $ruta === 'asesor.revisar' ? [$p, $nueva->uuid] : [$p];
            $html = $this->como('asesor@portal.local')->get(route("tesis-proyecto-tesis.{$ruta}", $params))->assertOk()->getContent();
            $this->assertEnlaceAntesDeLevantar($html, $nueva);
            $this->assertStringContainsString('sin versión corregida indicada', $html, $ruta);
        }

        $enlace = route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $nueva->uuid]);
        $this->como('asesor@portal.local')->get($enlace)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->como('profesor@portal.local')->get($enlace)->assertForbidden();
        $this->como('jurado1@portal.local')->get($enlace)->assertForbidden();
    }

    public function test_jurado_ve_y_descarga_el_documento_corregido_de_la_revision_intermedia(): void
    {
        $p = $this->demo('08');

        // jurado1 deja una observación referencial en la revisión intermedia (desde el formulario renderizado).
        $html = $this->como('jurado1@portal.local')->get(route('tesis-proyecto-tesis.jurado.verificar', $p))->assertOk()->assertSee('Revisión intermedia (referencial)')->getContent();
        $marcas = $this->marcas('Tesis', [21 => 'no_cumple']);
        $this->como('jurado1@portal.local')->post(route('tesis-proyecto-tesis.jurado.verificar.guardar', $p),
            ['marcas' => $marcas, 'obs' => [21 => 'Faltan tablas interpretadas en resultados.'], 'accion' => 'referencial'])->assertSessionHasNoErrors();
        [$o] = $this->abiertasDe($p, 'jurado1@portal.local');

        $nueva = $this->responderConPdf($p, 'est08@portal.local', $o);

        $html = $this->como('jurado1@portal.local')->get(route('tesis-proyecto-tesis.jurado.verificar', $p))->assertOk()->getContent();
        $this->assertStringContainsString('Tus observaciones abiertas', $html);
        $this->assertEnlaceAntesDeLevantar($html, $nueva);

        $enlace = route('tesis-proyecto-tesis.archivos.descargar', ['entrega', $nueva->uuid]);
        $this->como('jurado1@portal.local')->get($enlace)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->como('jurado2@portal.local')->get($enlace)->assertOk();
        $this->como('profesor@portal.local')->get($enlace)->assertForbidden();
        $this->como('est04@portal.local')->get($enlace)->assertForbidden();

        // jurado1 la da por levantada desde la misma pantalla.
        $this->como('jurado1@portal.local')->post(route('tesis-proyecto-tesis.jurado.observaciones.levantar', [$p, $o->uuid]))->assertSessionHasNoErrors();
        $this->assertSame('Levantada', $o->refresh()->estado);
    }
}
