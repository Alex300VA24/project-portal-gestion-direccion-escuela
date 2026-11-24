<?php

namespace Tests\Feature\TesisProyectoTesis;

use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

/**
 * Envía un formulario tal como lo arma el navegador a partir del HTML renderizado, en lugar de un payload
 * armado a mano: solo viajan los campos que existen en el formulario, con sus valores por defecto.
 *
 * Reproduce además el comportamiento de Alpine en este módulo:
 * - un botón cuyo `:disabled` depende de `enviando` queda deshabilitado al enviar, así que su name/value
 *   NO se envía (es lo que rompía «Dar conformidad»);
 * - un `@click="$refs.x.value = '…'"` del botón pulsado llena el campo con `x-ref="x"` antes del envío.
 */
trait EnviaFormularios
{
    /**
     * @param  array<string, string|UploadedFile>  $datos  valores que el usuario escribe o elige, por nombre de campo (p. ej. 'marcas[3]')
     * @param  string|null  $boton  texto del botón de envío que se pulsa
     */
    protected function enviarFormulario(string $html, string $accion, array $datos = [], ?string $boton = null): TestResponse
    {
        // DOMDocument descarta los atributos @… y :… de Alpine: se renombran antes de leer el HTML.
        $html = preg_replace(['/\s@([\w.:-]+)=/', '/\s:([\w.-]+)=/'], [' x-on-$1=', ' x-bind-$1='], $html);
        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $x = new DOMXPath($dom);

        $form = null;
        foreach ($x->query('//form') as $f) {
            /** @var DOMElement $f */
            if ($f->getAttribute('action') === $accion) {
                $form = $f;
                break;
            }
        }
        $this->assertNotNull($form, "No hay un formulario con action $accion en la página.");

        $pares = [];      // [nombre, valor] en el orden del formulario
        $nombres = [];    // campos que existen
        $tipos = [];
        $refs = [];       // x-ref => nombre
        foreach ($x->query('.//input | .//select | .//textarea', $form) as $campo) {
            /** @var DOMElement $campo */
            $nombre = $campo->getAttribute('name');
            if ($nombre === '' || $campo->hasAttribute('disabled')) {
                continue;
            }
            $tipo = strtolower($campo->getAttribute('type') ?: ($campo->tagName === 'input' ? 'text' : $campo->tagName));
            $nombres[$nombre] = true;
            $tipos[$nombre] = $tipo;
            if ($campo->hasAttribute('x-ref')) {
                $refs[$campo->getAttribute('x-ref')] = $nombre;
            }
            $valor = match (true) {
                in_array($tipo, ['submit', 'button', 'image', 'reset', 'file'], true) => null,
                // Como el navegador: un radio/checkbox marcado envía su value (aunque sea ""); sin atributo value, «on».
                in_array($tipo, ['radio', 'checkbox'], true) => $campo->hasAttribute('checked') ? ($campo->hasAttribute('value') ? $campo->getAttribute('value') : 'on') : null,
                $campo->tagName === 'textarea' => $campo->textContent,
                $campo->tagName === 'select' => $this->valorSeleccionado($x, $campo),
                default => $campo->getAttribute('value'),
            };
            if ($valor !== null) {
                $pares[] = [$nombre, $valor];
            }
        }

        // Lo que el usuario escribe o elige: solo en campos que existen.
        $archivos = [];
        foreach ($datos as $nombre => $valor) {
            $this->assertArrayHasKey($nombre, $nombres, "El formulario no tiene el campo «{$nombre}».");
            if ($valor instanceof UploadedFile) {
                $archivos[$nombre] = $valor;

                continue;
            }
            $pares = array_values(array_filter($pares, fn ($p) => $p[0] !== $nombre || ($tipos[$nombre] === 'checkbox' && str_ends_with($nombre, '[]'))));
            $pares[] = [$nombre, (string) $valor];
        }

        // El botón pulsado.
        if ($boton !== null) {
            $elegido = null;
            foreach ($x->query('.//button', $form) as $b) {
                /** @var DOMElement $b */
                if (str_contains(preg_replace('/\s+/', ' ', $b->textContent), $boton) && in_array(strtolower($b->getAttribute('type') ?: 'submit'), ['submit'], true)) {
                    $elegido = $b;
                    break;
                }
            }
            $this->assertNotNull($elegido, "No hay un botón de envío «{$boton}».");
            if (preg_match_all("/\\\$refs\\.(\\w+)\\.value\\s*=\\s*'([^']*)'/", $elegido->getAttribute('x-on-click'), $m, PREG_SET_ORDER)) {
                foreach ($m as [, $ref, $valor]) {
                    $this->assertArrayHasKey($ref, $refs, "El botón llena x-ref «{$ref}», que no existe.");
                    $pares = array_values(array_filter($pares, fn ($p) => $p[0] !== $refs[$ref]));
                    $pares[] = [$refs[$ref], $valor];
                }
            }
            $deshabilitadoAlEnviar = str_contains($elegido->getAttribute('x-bind-disabled'), 'enviando') || $elegido->hasAttribute('disabled');
            if ($elegido->getAttribute('name') !== '' && ! $deshabilitadoAlEnviar) {
                $pares[] = [$elegido->getAttribute('name'), $elegido->getAttribute('value')];
            }
        }

        parse_str(implode('&', array_map(fn ($p) => urlencode($p[0]).'='.urlencode($p[1]), $pares)), $payload);
        foreach ($archivos as $nombre => $archivo) {
            data_set($payload, str_replace(['[', ']'], ['.', ''], $nombre), $archivo);
        }

        return strtoupper($form->getAttribute('method') ?: 'GET') === 'POST'
            ? $this->post($accion, $payload)
            : $this->get($accion.'?'.http_build_query($payload));
    }

    private function valorSeleccionado(DOMXPath $x, DOMElement $select): ?string
    {
        $opciones = $x->query('.//option', $select);
        foreach ($opciones as $o) {
            /** @var DOMElement $o */
            if ($o->hasAttribute('selected')) {
                return $o->hasAttribute('value') ? $o->getAttribute('value') : trim($o->textContent);
            }
        }
        $primera = $opciones->item(0);

        return $primera instanceof DOMElement ? ($primera->hasAttribute('value') ? $primera->getAttribute('value') : trim($primera->textContent)) : null;
    }
}
