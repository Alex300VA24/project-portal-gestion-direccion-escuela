<?php

namespace Tests\Feature\TesisProyectoTesis;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Interfaz común: menú lateral plegable con íconos y confirmación del coautor en el registro F1.
 * El comportamiento en el navegador (transición, tooltip, cajón en celular) se revisa a mano;
 * aquí se fija el marcado del que depende: ids, atributos ARIA, íconos y almacenamiento protegido.
 */
final class InterfazTest extends TesisTestCase
{
    /** Pantallas de cada carril: [cuenta, ruta, clave activa esperada, opciones del menú]. */
    private const CARRILES = [
        ['alumno@portal.local', 'alumno.panel', 'Inicio', 4],
        ['asesor@portal.local', 'asesor.proyectos', 'Mis proyectos', 1],
        ['jurado1@portal.local', 'jurado.sustentaciones', 'Sustentaciones', 2],
        ['director@portal.local', 'director.panel', 'Panel general', 2],
        ['secretaria@portal.local', 'secretaria.actas', 'Actas y dictámenes', 3],
    ];

    private function xpath(string $html): DOMXPath
    {
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();

        return new DOMXPath($dom);
    }

    public function test_menu_lateral_tiene_icono_nombre_accesible_y_opcion_actual_en_cada_carril(): void
    {
        // asesor@ y jurado1@ solo tienen carril si integran un proyecto.
        $this->proyecto(['alumno2@portal.local'], asesor: 'asesor@portal.local', jurados: ['Presidente' => 'jurado1@portal.local']);

        foreach (self::CARRILES as [$correo, $ruta, $activa, $opciones]) {
            $html = $this->como($correo)->get(route('tesis-proyecto-tesis.'.$ruta))->assertOk()->getContent();
            $x = $this->xpath($html);

            $boton = $x->query('//button[@aria-controls="tz-menu"]');
            $this->assertSame(1, $boton->length, "$ruta: un solo botón para el menú");
            /** @var DOMElement $b */
            $b = $boton->item(0);
            $this->assertSame('true', $b->getAttribute('aria-expanded'), "$ruta: desplegado por defecto");
            $this->assertSame('Contraer menú', $b->getAttribute('aria-label'));
            $this->assertStringContainsString("'Expandir menú'", $b->getAttribute(':aria-label'));
            $this->assertSame(1, $x->query('//aside[@id="tz-menu"]')->length, "$ruta: aria-controls apunta al menú");

            $enlaces = $x->query('//aside[@id="tz-menu"]//nav/a');
            $this->assertSame($opciones, $enlaces->length, "$ruta: opciones del menú");
            foreach ($enlaces as $a) {
                /** @var DOMElement $a */
                $etiqueta = trim($x->query('.//span[contains(@class, "tz-side-label")]', $a)->item(0)?->textContent ?? '');
                $this->assertNotSame('', $etiqueta, "$ruta: cada opción tiene nombre visible (tooltip en modo íconos)");
                $this->assertStringStartsWith($etiqueta, $a->getAttribute('aria-label'), "$ruta: aria-label con el nombre de «{$etiqueta}»");

                $svg = $x->query('./*[local-name()="svg"]', $a)->item(0);
                $this->assertInstanceOf(DOMElement::class, $svg, "$ruta: «{$etiqueta}» tiene ícono");
                $this->assertSame('true', $svg->getAttribute('aria-hidden'));
                $this->assertSame(0, $x->query('./*[local-name()="circle" and @r="8"]', $svg)->length, "$ruta: «{$etiqueta}» tiene un ícono propio, no el genérico");
            }

            $actual = $x->query('//aside[@id="tz-menu"]//a[@aria-current="page"]');
            $this->assertSame(1, $actual->length, "$ruta: una sola opción marcada como actual");
            $this->assertStringStartsWith($activa, $actual->item(0)->getAttribute('aria-label'));
        }
    }

    public function test_insignia_del_menu_se_anuncia_en_el_nombre_accesible(): void
    {
        $this->como('alumno@portal.local');
        $menu = [
            ['clave' => 'avances', 'etiqueta' => 'Avances', 'url' => '/x', 'insignia' => 1],
            ['clave' => 'observaciones', 'etiqueta' => 'Observaciones', 'url' => '/y', 'insignia' => 3],
            ['clave' => 'inicio', 'etiqueta' => 'Inicio', 'url' => '/z', 'insignia' => 0],
        ];
        $html = (string) $this->withViewErrors([])->view('modules.tesis-proyecto-tesis.layout', ['tesisNav' => [
            'rol' => 'alumno', 'rolEtiqueta' => 'Estudiante', 'activo' => 'inicio', 'usuario' => 'Prueba', 'iniciales' => 'PR',
            'cambiarRol' => false, 'menu' => $menu, 'avisos' => [], 'noLeidas' => 0,
        ]]);
        $x = $this->xpath($html);
        $nombres = array_map(fn (DOMElement $a) => $a->getAttribute('aria-label'), iterator_to_array($x->query('//aside[@id="tz-menu"]//nav/a')));

        $this->assertSame(['Avances, 1 pendiente', 'Observaciones, 3 pendientes', 'Inicio'], $nombres);
        foreach ($x->query('//aside[@id="tz-menu"]//span[contains(@class, "tz-count")]') as $insignia) {
            $this->assertSame('true', $insignia->getAttribute('aria-hidden'), 'La cifra visible no se lee dos veces');
        }
    }

    public function test_menu_recuerda_la_eleccion_con_localstorage_protegido_y_se_cierra_en_celular(): void
    {
        $html = $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertOk()->getContent();

        // Todo acceso a localStorage va dentro de un try/catch.
        preg_match_all('/^.*localStorage.*$/m', $html, $lineas);
        $this->assertNotEmpty($lineas[0]);
        foreach ($lineas[0] as $linea) {
            $this->assertStringContainsString('try {', $linea, 'localStorage sin try/catch: '.trim($linea));
            $this->assertStringContainsString('catch (e)', $linea);
        }

        // Celular: cajón con velo que se cierra al tocar fuera y con Esc.
        // (Los atributos @… de Alpine no son nombres válidos para DOMDocument: se revisan sobre el HTML.)
        $this->assertMatchesRegularExpression('/<div class="tz" id="tz-raiz" x-data="tzShell\(\)"[^>]*@keydown\.escape\.window="escape\(\)"/', $html);
        $this->assertMatchesRegularExpression('/<div class="tz-velo"[^>]*@click="cerrar\(\)"/', $html);
        $this->assertStringContainsString('(max-width: 900px)', $html);
    }

    public function test_portada_publica_no_usa_el_contenedor_del_modulo(): void
    {
        $html = $this->get(route('tesis-proyecto-tesis.index'))->assertOk()->assertSee('Iniciar sesión para continuar')->getContent();

        $this->assertSame(0, $this->xpath($html)->query('//button[@aria-controls="tz-menu"]')->length);
        $this->assertStringNotContainsString('tz-topbar', $html);
    }

    public function test_contenedor_propio_sin_la_navegacion_del_portal(): void
    {
        $this->proyecto(['alumno2@portal.local'], asesor: 'asesor@portal.local', jurados: ['Presidente' => 'jurado1@portal.local']);

        foreach (self::CARRILES as [$correo, $ruta]) {
            $html = $this->como($correo)->get(route('tesis-proyecto-tesis.'.$ruta))->assertOk()->getContent();
            $x = $this->xpath($html);

            // Documento propio del módulo: sin la barra del portal (logo, «Dashboard», nombre).
            $this->assertSame(1, substr_count($html, '<html'), "$ruta: un solo documento");
            $this->assertStringNotContainsString('<nav x-data="{ open: false }"', $html, "$ruta: sin la navegación del portal");
            $this->assertDoesNotMatchRegularExpression('/>\s*Dashboard\s*</', $html, "$ruta: sin el enlace «Dashboard» del portal");
            $this->assertSame(1, $x->query('//body[@class="tz-body"]')->length);

            // Menú lateral con la marca del módulo y barra superior con las migas.
            $marca = $x->query('//aside[@id="tz-menu"]/a[contains(@class, "tz-brand")]')->item(0);
            $this->assertStringContainsString('Gestión de Tesis', $marca->textContent);
            $this->assertStringContainsString('Escuela de Ingeniería Informática', $marca->textContent);
            $migas = $x->query('//header[contains(@class, "tz-topbar")]//nav[@aria-label="Ruta actual"]/a');
            $this->assertSame(['Portal', 'Tesis y Proyecto de Tesis'], array_map(fn (DOMElement $a) => trim($a->textContent), iterator_to_array($migas)));
            $this->assertSame(route('dashboard'), $migas->item(0)->getAttribute('href'));

            // Livewire y Vite según docs/DESIGN.md (sin Alpine aparte).
            $this->assertStringNotContainsString('alpinejs', $html, "$ruta: Alpine no se carga por separado");
        }
    }

    public function test_menu_de_usuario_con_perfil_cerrar_sesion_y_cambiar_de_modulo(): void
    {
        $html = $this->como('alumno@portal.local')->get(route('tesis-proyecto-tesis.alumno.panel'))->assertOk()->getContent();
        $x = $this->xpath($html);

        $panel = $x->query('//div[@id="tz-usuario"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $panel);
        $disparador = $x->query('//button[@aria-controls="tz-usuario"]')->item(0);
        $this->assertSame('false', $disparador->getAttribute('aria-expanded'));

        $perfil = $x->query('.//a[normalize-space()="Mi perfil"]', $panel)->item(0);
        $this->assertSame(route('profile.edit'), $perfil->getAttribute('href'));

        $salir = $x->query('.//form[.//button[normalize-space()="Cerrar sesión"]]', $panel)->item(0);
        $this->assertSame('POST', strtoupper($salir->getAttribute('method')));
        $this->assertSame(route('logout'), $salir->getAttribute('action'));
        $this->assertSame(1, $x->query('.//input[@name="_token"]', $salir)->length, 'Cerrar sesión lleva @csrf');

        $cambiar = $x->query('//aside[@id="tz-menu"]/a[contains(@class, "tz-module-switch")]')->item(0);
        $this->assertSame(route('dashboard'), $cambiar->getAttribute('href'));
        $this->assertSame('Cambiar de módulo', $cambiar->getAttribute('aria-label'));
        $this->assertSame(1, $x->query('./*[local-name()="svg"]', $cambiar)->length, 'Cambiar de módulo tiene ícono');
        $this->assertSame('Cambiar de módulo', trim($x->query('.//span[contains(@class, "tz-side-label")]', $cambiar)->item(0)->textContent), 'Nombre visible y tooltip en modo plegado');

        // Un solo rol: no aparece «Cambiar rol».
        $this->assertSame(0, $x->query('.//a[normalize-space()="Cambiar rol"]', $panel)->length);
    }

    public function test_cambiar_rol_aparece_solo_con_mas_de_un_rol(): void
    {
        // asesor@ asesora un proyecto y es jurado de otro: tiene dos carriles.
        $this->proyecto(['alumno2@portal.local'], asesor: 'asesor@portal.local');
        $this->proyecto(['alumno3@portal.local'], asesor: 'profesor@portal.local', jurados: ['Vocal' => 'asesor@portal.local']);

        $html = $this->como('asesor@portal.local')->get(route('tesis-proyecto-tesis.asesor.proyectos'))->assertOk()->getContent();
        $enlace = $this->xpath($html)->query('//div[@id="tz-usuario"]//a[normalize-space()="Cambiar rol"]')->item(0);

        $this->assertInstanceOf(DOMElement::class, $enlace);
        $this->assertStringStartsWith(route('tesis-proyecto-tesis.index'), $enlace->getAttribute('href'));
    }

    public function test_boton_de_tema_usa_la_preferencia_comun_del_portal(): void
    {
        $html = $this->como('director@portal.local')->get(route('tesis-proyecto-tesis.director.panel'))->assertOk()->getContent();
        $x = $this->xpath($html);

        $boton = $x->query('//header[contains(@class, "tz-topbar")]//button[@id="tz-tema"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $boton);
        $this->assertSame('button', $boton->getAttribute('type'));
        $this->assertSame('Cambiar a tema oscuro', $boton->getAttribute('aria-label'));
        $this->assertSame(2, $x->query('./*[local-name()="svg"]', $boton)->length, 'Íconos de luna y sol');

        // La preferencia se lee en el <head>, antes de pintar, con la misma clave que el resto del portal.
        [$head] = explode('</head>', $html, 2);
        $this->assertStringContainsString("localStorage.getItem('portal-theme')", $head);
        $this->assertStringContainsString("d.theme = tema === 'dark' ? 'dark' : 'light'", $head);
        $this->assertStringContainsString("localStorage.setItem('portal-theme', tema)", $html);
        // Valores oscuros de las variables del módulo.
        $this->assertStringContainsString('html[data-theme="dark"] .tz-body', $head);
    }

    public function test_registro_confirma_integrante_2_con_opcion_quitar(): void
    {
        $html = $this->como('alumno3@portal.local')->get(route('tesis-proyecto-tesis.alumno.proyecto.registrar'))->assertOk()->getContent();
        $x = $this->xpath($html);

        $estado = $x->query('//div[@id="coautor-estado"]')->item(0);
        $this->assertSame('status', $estado->getAttribute('role'));
        $this->assertSame('polite', $estado->getAttribute('aria-live'));
        $this->assertSame('coautor-estado', $x->query('//input[@id="coautor_codigo"]')->item(0)->getAttribute('aria-describedby'));

        $this->assertStringContainsString('Se agregará como integrante 2 al registrar el proyecto.', $html);
        $this->assertStringContainsString('No necesitas pulsar otro botón', $html);
        $quitar = $x->query('//div[@data-coautor="listo"]//button[normalize-space()="Quitar"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $quitar);
        $this->assertSame('button', $quitar->getAttribute('type'), 'Quitar no envía el formulario');
        $this->assertMatchesRegularExpression('/<button type="button" class="tz-btn tz-btn-secondary" @click="quitarCoautor\(\)"[^>]*>Quitar<\/button>/', $html);
        $this->assertSame(1, $x->query('//button[@type="submit" and normalize-space()="Registrar proyecto"]')->length, 'El único botón de envío es Registrar proyecto');

        // La búsqueda entrega lo que la confirmación muestra: nombre, código y disponibilidad.
        $codigo = (string) \Illuminate\Support\Facades\DB::table('core.estudiante as e')
            ->join('seguridad.usuario as u', 'u.id_persona', '=', 'e.id_persona')
            ->where('u.correo_institucional', 'alumno2@portal.local')->value('e.codigo_universitario');
        $this->como('alumno3@portal.local')->getJson(route('tesis-proyecto-tesis.alumno.coautor', ['codigo' => $codigo]))
            ->assertOk()->assertJson(['encontrado' => true, 'codigo' => $codigo, 'disponible' => true]);
    }
}
