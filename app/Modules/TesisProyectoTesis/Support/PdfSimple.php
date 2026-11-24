<?php

namespace App\Modules\TesisProyectoTesis\Support;

/**
 * Genera un PDF válido de una página con texto (Helvetica, WinAnsi). Se usa para los documentos de ejemplo
 * del seeder de escenarios y para la constancia en PDF del oficio de solicitud de asesor; no reemplaza a los
 * documentos que suben los usuarios.
 */
final class PdfSimple
{
    /**
     * @param  list<string>  $lineas
     */
    public static function generar(string $titulo, array $lineas = []): string
    {
        $texto = 'BT /F1 16 Tf 56 780 Td ('.self::escapar($titulo).') Tj ET'."\n";
        $y = 750;
        foreach ($lineas as $linea) {
            foreach (self::partir($linea, 95) as $trozo) {
                if ($y < 60) {
                    break 2;
                }
                $texto .= 'BT /F1 11 Tf 56 '.$y.' Td ('.self::escapar($trozo).') Tj ET'."\n";
                $y -= 16;
            }
        }

        $objetos = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            '<< /Length '.strlen($texto)." >>\nstream\n".$texto."endstream",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objetos as $i => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n".$obj."\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objetos) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= str_pad((string) $o, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objetos) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF\n";
    }

    private static function escapar(string $s): string
    {
        $s = mb_convert_encoding($s, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ' '], $s);
    }

    /** @return list<string> */
    private static function partir(string $linea, int $ancho): array
    {
        return $linea === '' ? [''] : explode("\n", wordwrap($linea, $ancho, "\n", true));
    }
}
