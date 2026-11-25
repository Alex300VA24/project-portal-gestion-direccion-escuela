<?php

namespace Tests\Unit\CajaBlanca;

use App\Modules\Bienestar\Support\HtmlSanitizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Caja blanca: recorre cada rama de HtmlSanitizer (XSS almacenado en artículos). */
final class HtmlSanitizerTest extends TestCase
{
    #[DataProvider('ataques')]
    public function test_elimina_contenido_peligroso(string $html): void
    {
        $limpio = strtolower(HtmlSanitizer::clean($html));

        foreach (['<script', '<iframe', '<img', '<svg', '<style', '<a ', 'onerror', 'onclick', 'onload', 'javascript:'] as $peligro) {
            $this->assertStringNotContainsString($peligro, $limpio);
        }
    }

    public static function ataques(): array
    {
        return [
            'script' => ['<p>a</p><script>alert(1)</script>'],
            'script sin cierre' => ['<p>a</p><script src=x>'],
            'iframe' => ['<iframe src="x"></iframe>'],
            'evento en permitida' => ['<p onclick="x()">a</p>'],
            'evento en anidada' => ['<div><p><b onclick="x()">a</b></p></div>'],
            'no permitida' => ['<img src=x onerror=alert(1)>'],
            'peligro dentro de no permitida' => ['<section><img src=x onerror=alert(1)></section>'],
            'evento dentro de no permitida' => ['<section><p onclick="x()">a</p></section>'],
            'doble no permitida' => ['<section><article><svg onload=alert(1)></svg></article></section>'],
            'varios hijos en no permitida' => ['<a><p>1</p><img src=x onerror=alert(1)><p onclick="x()">2</p></a>'],
            'enlace javascript' => ['<a href="javascript:alert(1)">x</a>'],
            'title javascript' => ['<p title="JavaScript:alert(1)">x</p>'],
        ];
    }

    public function test_vacio_devuelve_vacio(): void
    {
        $this->assertSame('', HtmlSanitizer::clean("  \n "));
    }

    public function test_conserva_formato_permitido_y_tildes(): void
    {
        $this->assertSame('<p class="x"><strong>Matrícula ñ</strong></p>', HtmlSanitizer::clean('<p class="x"><strong>Matrícula ñ</strong></p>'));
    }

    public function test_conserva_el_texto_de_etiquetas_no_permitidas(): void
    {
        $this->assertSame('<p>uno</p>dos', HtmlSanitizer::clean('<section><p>uno</p>dos</section>'));
    }
}
