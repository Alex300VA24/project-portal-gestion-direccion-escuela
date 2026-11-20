<?php

namespace App\Modules\SeguimientoEgresado\Services\Reportes;

use RuntimeException;
use ZipArchive;

/**
 * Libro de Excel (.xlsx) con una o varias hojas, escrito directamente con ZipArchive para no agregar dependencias.
 * Todo texto va como cadena en línea, así que Excel nunca lo interpreta como fórmula.
 * Si el servidor no tiene ZipArchive, aCsv() entrega el mismo contenido como texto separado por punto y coma.
 */
final class LibroExcel
{
    public const TITULO = 1;

    public const SUBTITULO = 2;

    public const ETIQUETA = 3;

    public const VALOR = 4;

    public const ENCABEZADO = 5;

    public const TEXTO = 6;

    public const CENTRO = 7;

    public const MARCA = 8;

    public const GRUPO = 9;

    public const NOTA = 10;

    public const CAJA = 11;

    public const FIRMA = 12;

    public static function disponible(): bool
    {
        return class_exists(ZipArchive::class);
    }

    public static function letra(int $indice): string
    {
        return chr(65 + $indice);
    }

    /**
     * @param  list<HojaExcel>  $hojas
     * @return string ruta del archivo temporal generado
     */
    public function generar(array $hojas): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'formato');
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->tiposDeContenido(count($hojas)));
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $this->libro($hojas));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacionesLibro(count($hojas)));
        $zip->addFromString('xl/styles.xml', $this->estilos());
        foreach ($hojas as $i => $hoja) {
            $zip->addFromString('xl/worksheets/sheet'.($i + 1).'.xml', $this->hoja($hoja));
        }
        $zip->close();

        return $ruta;
    }

    /**
     * Respaldo sin ZipArchive: texto con BOM, punto y coma (el separador de Excel en Perú) y sin fórmulas ejecutables.
     *
     * @param  list<HojaExcel>  $hojas
     */
    public function aCsv(array $hojas): string
    {
        $salida = fopen('php://temp', 'w+');
        fwrite($salida, "\xEF\xBB\xBF");

        foreach ($hojas as $i => $hoja) {
            if ($i > 0) {
                fputcsv($salida, [], ';', escape: '');
            }
            foreach ($hoja->filas() as [$celdas]) {
                $textos = array_map(fn ($c) => $c === null ? '' : (preg_match('/^[=+\-@\t\r]/', $c[0]) ? "'".$c[0] : $c[0]), $celdas);
                fputcsv($salida, $textos, ';', escape: '');
            }
        }
        rewind($salida);
        $contenido = stream_get_contents($salida);
        fclose($salida);

        return $contenido;
    }

    private function hoja(HojaExcel $hoja): string
    {
        $filas = '';
        foreach ($hoja->filas() as $i => [$celdas, $alto]) {
            $n = $i + 1;
            $altura = $alto === null ? '' : " ht=\"{$alto}\" customHeight=\"1\"";
            $xml = '';
            foreach ($celdas as $j => $celda) {
                if ($celda === null) {
                    continue;
                }
                $texto = htmlspecialchars($celda[0], ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= '<c r="'.self::letra($j).$n.'" s="'.$celda[1].'" t="inlineStr"><is><t xml:space="preserve">'.$texto.'</t></is></c>';
            }
            $filas .= "<row r=\"{$n}\"{$altura}>{$xml}</row>";
        }

        $columnas = '';
        foreach ($hoja->anchos as $i => $ancho) {
            $c = $i + 1;
            $columnas .= "<col min=\"{$c}\" max=\"{$c}\" width=\"{$ancho}\" customWidth=\"1\"/>";
        }
        $combinadas = $hoja->combinadas();
        $unidas = $combinadas === [] ? '' : '<mergeCells count="'.count($combinadas).'">'.implode('', array_map(fn ($r) => '<mergeCell ref="'.$r.'"/>', $combinadas)).'</mergeCells>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"/></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="16"/>'
            .'<cols>'.$columnas.'</cols>'
            .'<sheetData>'.$filas.'</sheetData>'
            .$unidas
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    private function tiposDeContenido(int $hojas): string
    {
        $partes = '';
        for ($i = 1; $i <= $hojas; $i++) {
            $partes .= '<Override PartName="/xl/worksheets/sheet'.$i.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .$partes
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    /** @param  list<HojaExcel>  $hojas */
    private function libro(array $hojas): string
    {
        $nombres = '';
        foreach ($hojas as $i => $hoja) {
            $n = $i + 1;
            $nombres .= '<sheet name="'.htmlspecialchars($hoja->nombre, ENT_XML1 | ENT_QUOTES, 'UTF-8')."\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$nombres.'</sheets></workbook>';
    }

    private function relacionesLibro(int $hojas): string
    {
        $partes = '';
        for ($i = 1; $i <= $hojas; $i++) {
            $partes .= '<Relationship Id="rId'.$i.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$i.'.xml"/>';
        }
        $estilos = $hojas + 1;

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .$partes
            .'<Relationship Id="rId'.$estilos.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /** Colores de la guía visual: marino #172554, texto #15233d, ayuda #536078, borde #dde1da. */
    private function estilos(): string
    {
        $xf = fn (int $fuente, int $relleno, int $borde, string $alineacion) => '<xf numFmtId="0" fontId="'.$fuente.'" fillId="'.$relleno.'" borderId="'.$borde.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment '.$alineacion.'/></xf>';
        $izquierda = 'horizontal="left" vertical="center" wrapText="1"';
        $centro = 'horizontal="center" vertical="center" wrapText="1"';

        $formatos = [
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
            $xf(1, 2, 0, 'horizontal="left" vertical="center"'),   // título
            $xf(2, 0, 0, 'horizontal="left" vertical="center"'),   // subtítulo
            $xf(3, 0, 0, 'horizontal="left" vertical="center"'),   // etiqueta
            $xf(0, 0, 0, 'horizontal="left" vertical="center"'),   // valor
            $xf(4, 2, 1, $centro),                                 // encabezado de columna
            $xf(0, 0, 1, $izquierda),                              // texto
            $xf(0, 0, 1, $centro),                                 // centrado
            $xf(5, 3, 1, $centro),                                 // marca (X)
            $xf(3, 3, 1, 'horizontal="left" vertical="center"'),   // grupo
            $xf(2, 0, 0, $izquierda),                              // nota
            $xf(0, 0, 1, 'horizontal="left" vertical="top" wrapText="1"'), // caja de texto
            $xf(3, 0, 0, $centro),                                 // firma
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="6">'
            .'<font><sz val="11"/><color rgb="FF15233D"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="14"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF536078"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF172554"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="13"/><color rgb="FF172554"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="4">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF172554"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF0FF"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFDDE1DA"/></left><right style="thin"><color rgb="FFDDE1DA"/></right><top style="thin"><color rgb="FFDDE1DA"/></top><bottom style="thin"><color rgb="FFDDE1DA"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($formatos).'">'.implode('', $formatos).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
