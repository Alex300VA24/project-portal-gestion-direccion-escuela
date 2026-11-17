<?php

namespace Tests\Feature\SeguimientoEgresado;

use DOMDocument;
use DOMXPath;
use Illuminate\Testing\TestResponse;
use Tests\Support\SeguimientoEgresado\ComoPersonal;
use Tests\Support\SeguimientoEgresado\UsaBaseDeDatos;
use Tests\TestCase;

/** Armazón del módulo dentro del portal: portada pública, páginas internas con la cuenta del portal y navegación activa. */
class SeguimientoShellTest extends TestCase
{
    use ComoPersonal, UsaBaseDeDatos;

    public function test_todas_las_paginas_internas_se_ven_con_la_navegacion_activa_correcta(): void
    {
        $this->actingAs($this->secretaria());

        foreach (['index', 'padron', 'campanias', 'empresas', 'indicadores', 'institucional'] as $nombre) {
            $respuesta = $this->get(route('seguimiento-egresado.'.$nombre));

            $respuesta->assertOk()
                ->assertSee('href="#contenido"', false)
                ->assertSee('id="contenido"', false)
                ->assertSee('aria-controls="sg-navigation"', false);

            $activos = $this->xpath($respuesta)->query('//nav[@id="sg-navigation"]//a[@aria-current="page"]');

            $this->assertCount(1, $activos, "navegación activa en {$nombre}");
            $this->assertSame(route('seguimiento-egresado.'.$nombre), $activos->item(0)->getAttribute('href'));
        }
    }

    public function test_la_barra_superior_muestra_la_cuenta_del_portal_y_permite_cambiar_de_modulo_o_salir(): void
    {
        $usuario = $this->secretaria();

        $this->actingAs($usuario)->get(route('seguimiento-egresado.padron'))->assertOk()
            ->assertSee($usuario->name)
            ->assertSee('Cambiar de módulo')
            ->assertSee('Cerrar sesión')
            ->assertSee(route('logout'), false);
    }

    public function test_la_portada_es_publica_y_ofrece_las_cinco_areas_del_personal(): void
    {
        $respuesta = $this->get(route('seguimiento-egresado.index'));
        $tarjetas = $this->xpath($respuesta)->query('//main[@id="contenido"]//a[contains(concat(" ", normalize-space(@class), " "), " sg-tile ")]');
        $this->assertCount(5, $tarjetas);

        $destinos = [];
        foreach ($tarjetas as $tarjeta) {
            $destinos[] = $tarjeta->getAttribute('href');
        }

        $this->assertEqualsCanonicalizing(
            array_map(fn ($nombre) => route('seguimiento-egresado.'.$nombre), ['padron', 'campanias', 'empresas', 'indicadores', 'institucional']),
            $destinos,
        );

        $respuesta->assertOk()
            ->assertSee('Inicio · Seguimiento al Egresado | UNT')
            ->assertSee('Iniciar sesión')
            ->assertSee('seguimiento_egresado');
    }

    public function test_sin_sesion_las_paginas_internas_llevan_al_acceso_del_portal(): void
    {
        foreach (['padron', 'campanias', 'empresas', 'indicadores', 'institucional', 'reportes'] as $nombre) {
            $this->get(route('seguimiento-egresado.'.$nombre))->assertRedirect(route('login'));
        }
    }

    private function xpath(TestResponse $respuesta): DOMXPath
    {
        $anterior = libxml_use_internal_errors(true);

        try {
            $documento = new DOMDocument;
            $documento->loadHTML($respuesta->getContent(), LIBXML_NONET);

            return new DOMXPath($documento);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($anterior);
        }
    }
}
