<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Revision;
use App\Modules\TesisProyectoTesis\Support\Estados;

/**
 * Regresión de «Dar conformidad → validation.required»: la rúbrica se envía con los campos del HTML
 * renderizado (EnviaFormularios), no con un payload armado a mano.
 */
final class RubricaFormularioTest extends TesisTestCase
{
    use EnviaFormularios;

    protected function setUp(): void
    {
        parent::setUp();
        $this->irASemanaNueveDelPeriodo(); // PT-DEMO-08 en revisión intermedia
        $this->seed(EscenariosTesisSeeder::class);
    }

    private function p(string $n): Proyecto
    {
        return Proyecto::query()->where('codigo', EscenariosTesisSeeder::PREFIJO.$n)->firstOrFail();
    }

    /** @return array<string, string> todas las marcas del formulario en «cumple», salvo las indicadas */
    private function marcasDelHtml(string $html, array $otras = []): array
    {
        preg_match_all('/name="marcas\[(\d+)\]"/', $html, $m);
        $marcas = [];
        foreach (array_unique($m[1]) as $orden) {
            $marcas["marcas[$orden]"] = $otras[(int) $orden] ?? 'cumple';
        }
        $this->assertNotEmpty($marcas);

        return $marcas;
    }

    public function test_asesor_da_conformidad_con_el_formulario_renderizado(): void
    {
        $p = $this->p('05');
        $html = $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.verificar', $p))->assertOk()->getContent();

        $this->enviarFormulario($html, route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), $this->marcasDelHtml($html), 'Dar conformidad')
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('tesis-proyecto-tesis.asesor.similitud', $p));

        $this->assertTrue((bool) Revision::query()->where('id_docente', $this->idDocente('asesor@portal.local'))->latest('id_revision')->firstOrFail()->conforme);
    }

    public function test_asesor_envia_observaciones_con_un_no_cumple(): void
    {
        $p = $this->p('05');
        $html = $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.verificar', $p))->assertOk()->getContent();
        $datos = $this->marcasDelHtml($html, [3 => 'no_cumple']) + ['obs[3]' => 'Justifica la relevancia con datos.'];

        $this->enviarFormulario($html, route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), $datos, 'Guardar y enviar observaciones')
            ->assertSessionHasNoErrors();

        $this->assertSame(Estados::EN_DESARROLLO, $p->refresh()->estado);
        $this->assertFalse((bool) Revision::query()->where('id_docente', $this->idDocente('asesor@portal.local'))->latest('id_revision')->firstOrFail()->conforme);
    }

    public function test_jurados_dan_conformidad_con_el_formulario_renderizado(): void
    {
        $p = $this->p('07');
        foreach (['jurado2@portal.local', 'jurado3@portal.local'] as $jurado) {
            $html = $this->como($jurado)->get(route('tesis-proyecto-tesis.jurado.verificar', $p))->assertOk()->getContent();

            $this->enviarFormulario($html, route('tesis-proyecto-tesis.jurado.verificar.guardar', $p), $this->marcasDelHtml($html), 'Dar conformidad')
                ->assertSessionHasNoErrors();

            $this->assertTrue((bool) Revision::query()->where('id_docente', $this->idDocente($jurado))->latest('id_revision')->firstOrFail()->conforme, $jurado);
        }
    }

    public function test_jurado_registra_la_revision_intermedia_con_el_formulario_renderizado(): void
    {
        $p = $this->p('08');
        $html = $this->como('jurado1@portal.local')->get(route('tesis-proyecto-tesis.jurado.verificar', $p))->assertOk()->assertSee('Revisión intermedia')->getContent();
        $datos = $this->marcasDelHtml($html, [21 => 'no_cumple']) + ['obs[21]' => 'Faltan tablas interpretadas.'];

        $this->enviarFormulario($html, route('tesis-proyecto-tesis.jurado.verificar.guardar', $p), $datos, 'Registrar revisión intermedia')
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) Revision::query()->where('tipo', 'Intermedia')->where('id_docente', $this->idDocente('jurado1@portal.local'))->firstOrFail()->referencial);
    }

    public function test_la_accion_no_depende_del_name_de_un_boton_que_alpine_deshabilita(): void
    {
        $p = $this->p('05');
        $html = $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.verificar', $p))->assertOk()->getContent();

        // Ningún botón de envío de la rúbrica lleva name: la acción va en el campo oculto que llena el clic.
        $this->assertDoesNotMatchRegularExpression('/<button type="submit" name="accion"/', $html);
        $this->assertMatchesRegularExpression('/<input type="hidden" name="accion" x-ref="accion"/', $html);
        $this->assertStringContainsString('@submit="setTimeout(() => enviando = true)"', $html);

        // Control: con el marcado anterior (acción en el botón) el mismo envío pierde «accion».
        $antiguo = preg_replace(
            ['/<input type="hidden" name="accion" x-ref="accion"[^>]*>/', '/data-accion="conformidad" @click="[^"]*"/', '/@submit="setTimeout\(\(\) => enviando = true\)"/'],
            ['', 'name="accion" value="conformidad"', '@submit="enviando = true"'],
            $html,
        );
        $this->enviarFormulario($antiguo, route('tesis-proyecto-tesis.asesor.verificar.guardar', $p), $this->marcasDelHtml($html), 'Dar conformidad')
            ->assertSessionHasErrors('accion');
    }
}
