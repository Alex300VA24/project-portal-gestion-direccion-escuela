<?php

namespace Tests\Feature\TesisProyectoTesis;

use App\Modules\TesisProyectoTesis\Database\Seeders\EscenariosTesisSeeder;
use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Entrega;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Sustentacion;
use DOMDocument;
use DOMXPath;

/**
 * Cada pantalla de detalle o formulario lleva «← Volver a …» hacia su lista de origen, y al guardar con éxito
 * se vuelve a esa lista con el mensaje de confirmación.
 */
final class VolverTest extends TesisTestCase
{
    use EnviaFormularios;

    private const P = 'tesis-proyecto-tesis.';

    protected function setUp(): void
    {
        parent::setUp();
        $this->irASemanaNueveDelPeriodo();
        $this->seed(EscenariosTesisSeeder::class);
    }

    private function p(string $n): Proyecto
    {
        return Proyecto::query()->where('codigo', EscenariosTesisSeeder::PREFIJO.$n)->firstOrFail();
    }

    /** @return array{0: string, 1: string} [href, texto] del enlace «Volver». */
    private function volver(string $html): array
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $a = (new DOMXPath($dom))->query('//main//a[contains(@class, "tz-back")]');
        $this->assertSame(1, $a->length, 'Un solo enlace «Volver».');

        return [$a->item(0)->getAttribute('href'), trim(preg_replace('/\s+/', ' ', $a->item(0)->textContent))];
    }

    public function test_cada_pantalla_de_detalle_vuelve_a_su_lista(): void
    {
        $e03 = Entrega::query()->where('id_proyecto', $this->p('03')->id_proyecto)->where('estado', 'Pendiente')->firstOrFail();
        $acta = Acta::query()->where('id_proyecto', $this->p('11')->id_proyecto)->where('tipo', 'Sustentacion')->firstOrFail();
        $s10 = Sustentacion::query()->where('id_proyecto', $this->p('10')->id_proyecto)->firstOrFail();

        $casos = [
            ['nuevo@portal.local', route(self::P.'alumno.proyecto.registrar'), route(self::P.'alumno.panel'), 'Inicio'],
            ['asesor@portal.local', route(self::P.'asesor.proyecto', $this->p('03')), route(self::P.'asesor.proyectos'), 'Mis proyectos'],
            ['asesor@portal.local', route(self::P.'asesor.revisar', [$this->p('03'), $e03]), route(self::P.'asesor.proyectos'), 'Mis proyectos'],
            ['asesor@portal.local', route(self::P.'asesor.verificar', $this->p('05')), route(self::P.'asesor.proyectos'), 'Mis proyectos'],
            ['asesor@portal.local', route(self::P.'asesor.similitud', $this->p('05')), route(self::P.'asesor.proyectos'), 'Mis proyectos'],
            ['director@portal.local', route(self::P.'director.asesor.solicitar', $this->p('01')), route(self::P.'director.panel'), 'Panel general'],
            ['director@portal.local', route(self::P.'director.jurado', $this->p('06')), route(self::P.'director.panel'), 'Panel general'],
            ['secretaria@portal.local', route(self::P.'secretaria.sustentaciones.programar', $this->p('09')), route(self::P.'secretaria.sustentaciones'), 'Sustentaciones'],
            ['secretaria@portal.local', route(self::P.'secretaria.actas.ver', $acta), route(self::P.'secretaria.actas'), 'Actas y dictámenes'],
            ['jurado2@portal.local', route(self::P.'jurado.verificar', $this->p('07')), route(self::P.'jurado.proyectos'), 'Proyectos asignados'],
            ['jurado3@portal.local', route(self::P.'jurado.resultado', $s10), route(self::P.'jurado.sustentaciones'), 'Sustentaciones'],
        ];

        foreach ($casos as [$correo, $pantalla, $lista, $nombre]) {
            $html = $this->como($correo)->get($pantalla)->assertOk()->getContent();
            $this->assertSame([$lista, "Volver a $nombre"], $this->volver($html), $pantalla);
        }
    }

    public function test_al_guardar_vuelve_a_la_lista_de_origen_con_el_mensaje(): void
    {
        // Jurado: rúbrica → Proyectos asignados.
        $p07 = $this->p('07');
        $html = $this->como('jurado2@portal.local')->get(route(self::P.'jurado.verificar', $p07))->getContent();
        preg_match_all('/name="marcas\[(\d+)\]"/', $html, $m);
        $marcas = array_fill_keys(array_map(fn ($o) => "marcas[$o]", array_unique($m[1])), 'cumple');
        $this->enviarFormulario($html, route(self::P.'jurado.verificar.guardar', $p07), $marcas, 'Dar conformidad')
            ->assertRedirect(route(self::P.'jurado.proyectos'))->assertSessionHas('status', 'PT-DEMO-07: Conformidad registrada.');

        // Jurado: resultado → Sustentaciones.
        $s10 = Sustentacion::query()->where('id_proyecto', $this->p('10')->id_proyecto)->firstOrFail();
        $html = $this->como('jurado3@portal.local')->get(route(self::P.'jurado.resultado', $s10))->getContent();
        $this->enviarFormulario($html, route(self::P.'jurado.resultado.registrar', $s10), ['resultado' => 'Aprobatorio'], 'Registrar mi resultado')
            ->assertRedirect(route(self::P.'jurado.sustentaciones'))->assertSessionHas('status');

        // Asesor: rúbrica con observaciones → Mis proyectos.
        $p05 = $this->p('05');
        $html = $this->como('asesor@portal.local')->get(route(self::P.'asesor.verificar', $p05))->getContent();
        preg_match_all('/name="marcas\[(\d+)\]"/', $html, $m);
        $marcas = array_fill_keys(array_map(fn ($o) => "marcas[$o]", array_unique($m[1])), 'cumple');
        $marcas['marcas[2]'] = 'no_cumple';
        $this->enviarFormulario($html, route(self::P.'asesor.verificar.guardar', $p05), $marcas + ['obs[2]' => 'Agrega datos que respalden el problema.'], 'Guardar y enviar observaciones')
            ->assertRedirect(route(self::P.'asesor.proyectos'))->assertSessionHas('status', 'Verificación de PT-DEMO-05 guardada. Las observaciones se enviaron a los estudiantes.');

        // Director: reemplazo de jurado → Panel general.
        $p06 = $this->p('06');
        $html = $this->como('director@portal.local')->get(route(self::P.'director.jurado', $p06))->getContent();
        $this->enviarFormulario($html, route(self::P.'director.jurado.asignar', $p06), ['Presidente' => 'DOC-102', 'Secretario' => 'DOC-103', 'Vocal' => 'DOC-104'], 'Asignar jurado')
            ->assertRedirect(route(self::P.'director.panel'));
        $html = $this->como('director@portal.local')->get(route(self::P.'director.jurado', $p06))->getContent();
        $this->enviarFormulario($html, route(self::P.'director.jurado.reemplazar', $p06), ['cargo' => 'Vocal', 'docente' => 'DOC-105', 'motivo' => 'Licencia por capacitación'], 'Reemplazar')
            ->assertRedirect(route(self::P.'director.panel'))->assertSessionHas('status', 'Se reemplazó al Vocal del jurado de PT-DEMO-06. Se notificó al nuevo miembro y al anterior.');
    }
}
