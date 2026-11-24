<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Database\Seeders\LineasInvestigacionSeeder;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Support\DuracionCronograma;
use Illuminate\Support\Facades\DB;

/**
 * Formulario F1 según el formato de la Escuela y su detalle en las fichas del estudiante, el asesor y el Director.
 */
final class FormatoF1Test extends TesisTestCase
{
    public function test_formulario_tiene_las_listas_ayudas_y_la_tabla_unica_de_presupuesto(): void
    {
        $html = $this->como('alumno3@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.registrar'))->assertOk()->getContent();

        foreach (['Cuantitativo', 'Cualitativo', 'Mixto', 'Básica', 'Aplicada', 'Experimental', 'Cuasi-experimental', 'Pre-experimental',
            'No experimental – descriptivo', 'No experimental – correlacional', '>Otro<'] as $opcion) {
            $this->assertStringContainsString($opcion, $html, $opcion);
        }
        $this->assertStringNotContainsString('>Bibliográfica<', $html);
        $this->assertStringContainsString('Ciudad o entidad donde se aplica la investigación (ej. Trujillo, La Libertad).', $html);
        $this->assertMatchesRegularExpression('/id="institucion"[^>]*value="Universidad Nacional de Trujillo"/', $html);
        $this->assertMatchesRegularExpression('/id="duracion_meses"[^>]*readonly/', $html);
        $this->assertDoesNotMatchRegularExpression('/name="duracion_meses"/', $html, 'La duración no se envía: se calcula.');
        $this->assertStringContainsString('no las etapas del trámite (F1/F2)', $html);
        $this->assertStringContainsString('Dedicación (h/sem.)', $html);

        // Tabla única de recursos y presupuesto con catálogo del clasificador.
        foreach (['2.3.1.5.1 · Materiales y útiles de oficina', '2.3.2.2.1 · Energía eléctrica', '2.3.2.2.2 · Telefonía e internet',
            '2.3.2.7.11 · Otros servicios (impresiones, empastado, anillado)', '– · Sin código (bien propio a costo 0)', 'Otro código'] as $c) {
            $this->assertStringContainsString('value="'.$c.'"', $html, $c);
        }
        foreach (['Total de bienes', 'Total de servicios', 'Total general', 'Valor unit. (S/)', 'Subtotal', 'Unidad'] as $t) {
            $this->assertStringContainsString($t, $html, $t);
        }
        $this->assertDoesNotMatchRegularExpression('/<option value="Local">/', $html, 'No se ofrece el tipo «Local».');
        $this->assertDoesNotMatchRegularExpression('/<option value="Personal">/', $html, 'El personal se agrega solo.');
        $this->assertStringContainsString('Asesor · lo designa el Decanato por resolución', $html);

        // Financiamiento con una ayuda por opción.
        foreach (['Los estudiantes cubren todos los gastos', 'La Universidad u otra institución pública', 'Una empresa, ONG o fondo concursable'] as $a) {
            $this->assertStringContainsString($a, $html, $a);
        }
    }

    public function test_duracion_del_primer_inicio_al_ultimo_termino(): void
    {
        $this->assertSame(1, DuracionCronograma::meses([['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30']]));
        $this->assertSame(6, DuracionCronograma::meses([
            ['fecha_inicio' => '2026-10-01', 'fecha_fin' => '2027-02-28'],
            ['fecha_inicio' => '2026-09-01', 'fecha_fin' => '2026-09-30'],
        ]));
        $this->assertNull(DuracionCronograma::meses([['fecha_inicio' => '', 'fecha_fin' => '']]));
    }

    public function test_las_fichas_muestran_el_f1_con_totales(): void
    {
        $this->irASemanaNueveDelPeriodo();
        $this->seed(EscenariosTesisSeeder::class);
        $p = Proyecto::query()->where('codigo', 'PT-DEMO-04')->firstOrFail();
        // Escenario: papel 1 × 28 + laptop 0 (bienes) e internet 6 × 60 + impresión 3 × 35 (servicios).
        $esperado = ['Pre-experimental', 'Trujillo, La Libertad', 'Redacción del informe', 'Materiales y útiles de oficina',
            'S/ 28.00', 'S/ 465.00', 'S/ 493.00', 'Total de bienes', 'Total de servicios', 'Camila Vásquez Ortiz · estudiante', 'Mg. Carlos Medina Ruiz · asesor'];

        $this->como('est04@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto'))->assertOk()->assertSeeInOrder(['Datos de la investigación (F1)', 'Cronograma de actividades', 'Recursos y presupuesto'])->assertSee($esperado);
        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyecto', $p))->assertOk()->assertSee($esperado);
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.proyecto', $p))->assertOk()->assertSee($esperado)
            ->assertSee('Volver a Todos los proyectos');
        $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.proyectos'))->assertSee(route('tesis-proyecto-tesis.director.proyecto', $p));

        // La ficha del Director solo la ve el Director.
        $this->como('est04@portal.local')->get(route('tesis-proyecto-tesis.director.proyecto', $p))->assertForbidden();
        $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.director.proyecto', $p))->assertForbidden();
    }

    public function test_seeder_de_lineas_queda_preparado_y_no_hace_nada_sin_lista(): void
    {
        $antes = DB::table('core.linea_investigacion')->orderBy('codigo')->get(['codigo', 'estado'])->all();
        $this->assertSame([], LineasInvestigacionSeeder::LINEAS);
        $this->seed(LineasInvestigacionSeeder::class);
        $this->assertEquals($antes, DB::table('core.linea_investigacion')->orderBy('codigo')->get(['codigo', 'estado'])->all());
    }
}
