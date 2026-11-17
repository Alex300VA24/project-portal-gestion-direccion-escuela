<?php

namespace App\Modules\Bienestar\Support;

use DOMDocument;
use DOMElement;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'small', 'span', 'div',
        'blockquote', 'hr', 'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private const ALLOWED_ATTRIBUTES = [
        'class', 'title',
    ];

    public static function clean(string $dirtyHtml): string
    {
        $dirtyHtml = trim($dirtyHtml);
        if ($dirtyHtml === '') {
            return '';
        }

        // Remover etiquetas script e iframe directamente antes de parsear
        $dirtyHtml = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $dirtyHtml);
        $dirtyHtml = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $dirtyHtml);
        $dirtyHtml = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $dirtyHtml);

        $dom = new DOMDocument;
        // Suprimir warnings por fragmentos HTML y usar codificación UTF-8
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?><div>'.$dirtyHtml.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        self::sanitizeNode($dom->documentElement);

        $cleaned = '';
        if ($dom->documentElement) {
            foreach ($dom->documentElement->childNodes as $child) {
                $cleaned .= $dom->saveHTML($child);
            }
        }

        return trim($cleaned);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if (! $child) {
                continue;
            }

            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);

                if (! in_array($tagName, self::ALLOWED_TAGS, true)) {
                    // Si el tag no está permitido, reemplazamos por sus nodos hijos o texto
                    while ($child->hasChildNodes()) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);

                    continue;
                }

                // Filtrar atributos: remover on*, style, src, href inseguros
                for ($j = $child->attributes->length - 1; $j >= 0; $j--) {
                    $attr = $child->attributes->item($j);
                    if (! $attr) {
                        continue;
                    }

                    $attrName = strtolower($attr->name);
                    if (! in_array($attrName, self::ALLOWED_ATTRIBUTES, true)) {
                        $child->removeAttribute($attr->name);
                    } elseif (str_starts_with($attrName, 'on') || str_contains(strtolower($attr->value), 'javascript:')) {
                        $child->removeAttribute($attr->name);
                    }
                }

                // Recursión para nodos hijos
                self::sanitizeNode($child);
            }
        }
    }
}
