<?php

namespace Tests\Feature\InvestigacionFormativa;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosInvestigacion;
use Tests\Feature\InvestigacionFormativa\Concerns\CreaDatosReporte;
use Tests\TestCase;

final class ExportacionPdfTest extends TestCase
{
    use CreaDatosInvestigacion;
    use CreaDatosReporte;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requiere PostgreSQL.');
        }
        $this->datos = $this->crearDatosInvestigacion();
    }

    public function test_descarga_el_reporte_como_pdf_adjunto(): void
    {
        $respuesta = $this->actingAs($this->datos['users']['director'])->get($this->ruta());

        $respuesta->assertOk();
        $this->assertSame('application/pdf', $respuesta->headers->get('content-type'));
        $this->assertSame('no-store, private', $respuesta->headers->get('cache-control'));
        $this->assertStringContainsString('attachment;', (string) $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString('investigacion-formativa-a-', (string) $respuesta->headers->get('content-disposition'));
        $this->assertStringContainsString('.pdf', (string) $respuesta->headers->get('content-disposition'));

        $contenido = (string) $respuesta->getContent();
        $this->assertStringStartsWith('%PDF', $contenido);
        $this->assertStringEndsWith('%%EOF', trim($contenido));
        $this->assertGreaterThan(1000, strlen($contenido));
    }

    public function test_el_pdf_incluye_los_datos_de_la_seccion_y_los_filtros_activos(): void
    {
        $proyecto = $this->proyecto();
        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($proyecto, $unidad, [3, 3, 1]);
        }
        $this->encuestaConRespuestas();

        $texto = $this->textoDelPdf(
            (string) $this->actingAs($this->datos['users']['director'])->get($this->ruta())->getContent()
        );

        $this->assertStringContainsString('Investigación Formativa I', $texto);
        $this->assertStringContainsString('Sección A', $texto);
        $this->assertStringContainsString('2026-II', $texto);
        $this->assertStringContainsString('Ingeniería Informática', $texto);
        $this->assertStringContainsString('Generado el', $texto);
        $this->assertStringContainsString('Todas las líneas', $texto);

        $this->assertStringContainsString('Calificaciones por grupo', $texto);
        $this->assertStringContainsString('Grupo A', $texto);
        $this->assertStringContainsString('14.00', $texto);
        $this->assertStringContainsString('Clasificación y trabajo destacado', $texto);

        $this->assertStringContainsString('Resultados de la encuesta', $texto);
        $this->assertStringContainsString('Pregunta 1', $texto);
        $this->assertStringContainsString('Distribución por pregunta', $texto);

        $this->assertStringContainsString('Notas de lectura', $texto);
        $this->assertStringContainsString('no incluye comentarios de la encuesta', $texto);
        $this->assertStringContainsString('no son los tres indicadores oficiales', $texto);
    }

    public function test_el_pdf_muestra_los_datos_pendientes_sin_inventar_ceros(): void
    {
        $this->proyecto();
        $this->encuestaConRespuestas();

        $texto = $this->textoDelPdf(
            (string) $this->actingAs($this->datos['users']['director'])->get($this->ruta())->getContent()
        );

        $this->assertStringContainsString('Pendiente', $texto);
        $this->assertStringContainsString('no reciben una nota final', $texto);
        $this->assertStringContainsString('No hay proyectos con las tres unidades evaluadas', $texto);
        $this->assertStringNotContainsString('Promedio de la sección:', $texto);
    }

    public function test_el_pdf_excluye_los_comentarios_confidenciales(): void
    {
        $this->encuestaConRespuestas();

        $texto = $this->textoDelPdf(
            (string) $this->actingAs($this->datos['users']['director'])->get($this->ruta())->getContent()
        );

        $this->assertStringNotContainsString('Comentario dos', $texto);
        $this->assertStringNotContainsString('Comentario tres', $texto);
        $this->assertStringNotContainsString('primero', $texto);
        $this->assertStringContainsString('Respuestas', $texto);
    }

    public function test_el_pdf_respeta_el_filtro_de_linea(): void
    {
        DB::table('core.linea_investigacion')->insert([
            'id_linea' => 9803,
            'id_escuela' => 920,
            'codigo' => 'LIN-C',
            'nombre' => 'Línea C',
            'estado' => 'Activo',
        ]);

        $proyectoA = $this->proyecto();
        $proyectoC = $this->otroProyecto('Proyecto de línea C', null, 9803);
        foreach ([1, 2, 3] as $unidad) {
            $this->evaluar($proyectoA, $unidad, [3, 3, 1]);
            $this->evaluar($proyectoC, $unidad, [4, 3, 1]);
        }

        $texto = $this->textoDelPdf(
            (string) $this->actingAs($this->datos['users']['director'])->get($this->ruta().'?linea=9803')->getContent()
        );

        $this->assertStringContainsString('Línea C', $texto);
        $this->assertStringContainsString('Proyecto de línea C', $texto);
        $this->assertStringNotContainsString('Grupo A', $texto);
        $this->assertStringContainsString('no se desagrega por línea', $texto);
    }

    public function test_la_exportacion_no_crea_registros_de_archivo(): void
    {
        $this->proyecto();
        $this->encuestaConRespuestas();

        $archivosAntes = DB::table('comun.archivo')->count();
        $respuestasAntes = DB::table('investigacion_formativa.encuesta_respuesta')->count();

        $this->actingAs($this->datos['users']['director'])->get($this->ruta())->assertOk();

        $this->assertSame($archivosAntes, DB::table('comun.archivo')->count());
        $this->assertSame($respuestasAntes, DB::table('investigacion_formativa.encuesta_respuesta')->count());

        $servicio = file_get_contents(app_path('Modules/InvestigacionFormativa/Services/ReportePdfService.php'));
        $this->assertStringNotContainsString('ArchivoGateway', $servicio);
        $this->assertStringNotContainsString('DB::', $servicio);
    }

    public function test_no_exporta_una_seccion_fuera_del_alcance(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.resultados.pdf', 9902))
            ->assertForbidden();
    }

    public function test_el_boton_de_descarga_conserva_el_filtro_de_linea(): void
    {
        $this->actingAs($this->datos['users']['director'])
            ->get(route('investigacion-formativa.director.resultados', 9901).'?linea=9801')
            ->assertOk()
            ->assertSee('Descargar PDF')
            ->assertSee('No incluye los comentarios confidenciales')
            ->assertSee('resultados.pdf?linea=9801', false);
    }

    private function ruta(int $seccion = 9901): string
    {
        return route('investigacion-formativa.director.resultados.pdf', $seccion);
    }

    /**
     * Extrae el texto del PDF con pdftotext para comprobar el contenido renderizado.
     */
    private function textoDelPdf(string $pdf): string
    {
        $this->assertTrue(
            (new Process(['which', 'pdftotext']))->run() === 0,
            'Se requiere pdftotext para verificar el contenido del PDF.'
        );

        $directorio = storage_path('app/dompdf/pruebas');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $ruta = $directorio.'/exportacion-'.uniqid('', true).'.pdf';
        file_put_contents($ruta, $pdf);

        $proceso = new Process(['pdftotext', '-layout', $ruta, '-']);
        $proceso->run();
        $texto = $proceso->getOutput();
        @unlink($ruta);

        $this->assertSame(0, $proceso->getExitCode(), 'pdftotext no pudo leer el PDF generado.');
        $this->assertNotSame('', trim($texto));

        return $this->normalizar($texto);
    }

    private function normalizar(string $texto): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $texto));
    }
}
