<?php

namespace App\Modules\SeguimientoEgresado\Services\Indicadores;

use RuntimeException;
use ZipArchive;

/**
 * Genera el reporte de I1–I6 como un archivo .xlsx con anchos, colores y formatos.
 * Se escribe directamente con ZipArchive para no agregar dependencias al proyecto.
 * Todo texto va como cadena en línea, así que Excel nunca lo interpreta como fórmula.
 */
final class ExcelIndicadores
{
    private const TITULO = 1;

    private const SUBTITULO = 2;

    private const ETIQUETA = 3;

    private const VALOR = 4;

    private const ENCABEZADO = 5;

    private const CODIGO = 6;

    private const TEXTO = 7;

    private const TEXTO_LARGO = 8;

    private const ENTERO = 9;

    private const PORCENTAJE = 10;

    private const MEJORAS = 11;

    private const SIN_VALOR = 12;

    private const CALCULADO = 13;

    private const PENDIENTE = 14;

    private const NOTA = 15;

    /** Anchos de las columnas A a K. */
    private const ANCHOS = [8, 30, 12, 11, 12.5, 11, 10, 12, 13, 25, 58];

    private const COLUMNAS = ['Código', 'Indicador', 'Resultado', 'Numerador', 'Denominador', 'Población', 'Muestra (N)', 'Cobertura', 'Estado', 'Fuente', 'Fórmula'];

    public static function disponible(): bool
    {
        return class_exists(ZipArchive::class);
    }

    /**
     * @param  array<string, ResultadoIndicador>  $indicadores
     * @return string ruta del archivo temporal generado
     */
    public function generar(FiltroIndicadores $filtro, array $indicadores, string $generado): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'indicadores');
        $zip = new ZipArchive;

        if ($zip->open($ruta, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No se pudo crear el archivo de Excel.');
        }

        $zip->addFromString('[Content_Types].xml', $this->tiposDeContenido());
        $zip->addFromString('_rels/.rels', $this->relacionesRaiz());
        $zip->addFromString('xl/workbook.xml', $this->libro());
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->relacionesLibro());
        $zip->addFromString('xl/styles.xml', $this->estilos());
        $zip->addFromString('xl/worksheets/sheet1.xml', $this->hoja($filtro, $indicadores, $generado));
        $zip->close();

        return $ruta;
    }

    /** @param  array<string, ResultadoIndicador>  $indicadores */
    private function hoja(FiltroIndicadores $filtro, array $indicadores, string $generado): string
    {
        $filas = [];
        $fila = 1;

        $filas[] = $this->fila($fila++, [$this->texto('A', 'Reporte de indicadores I1 a I6', self::TITULO)], 30);
        $filas[] = $this->fila($fila++, [$this->texto('A', 'Seguimiento al Egresado · Ingeniería Informática · Universidad Nacional de Trujillo', self::SUBTITULO)], 20);
        $fila++;

        foreach ([
            'Cohorte' => $indicadores['I1']->cohorte,
            'Periodo' => $filtro->periodo(),
            'Fecha de corte' => $filtro->corte->format('d/m/Y'),
            'Generado' => $generado,
        ] as $etiqueta => $valor) {
            $filas[] = $this->fila($fila++, [$this->texto('A', $etiqueta, self::ETIQUETA), $this->texto('C', $valor, self::VALOR)]);
        }
        $fila++;

        $inicioTabla = $fila;
        $encabezados = [];
        foreach (self::COLUMNAS as $i => $columna) {
            $encabezados[] = $this->texto($this->letra($i), $columna, self::ENCABEZADO);
        }
        $filas[] = $this->fila($fila++, $encabezados, 32);

        foreach ($indicadores as $r) {
            $filas[] = $this->fila($fila++, $this->celdasDeIndicador($r, $fila - 1), 46);
        }
        $finTabla = $fila - 1;
        $fila++;

        $notas = ['Cobertura: personas que respondieron respecto de las invitadas. No responder no significa estar sin empleo.'];
        foreach ($indicadores as $r) {
            if ($r->nota) {
                $notas[] = $r->codigo.': '.$r->nota;
            }
        }
        foreach ($notas as $nota) {
            $filas[] = $this->fila($fila++, [$this->texto('A', $nota, self::NOTA)]);
        }

        $columnas = '';
        foreach (self::ANCHOS as $i => $ancho) {
            $n = $i + 1;
            $columnas .= "<col min=\"{$n}\" max=\"{$n}\" width=\"{$ancho}\" customWidth=\"1\"/>";
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>'
            .'<sheetViews><sheetView showGridLines="0" workbookViewId="0"><pane ySplit="'.$inicioTabla.'" topLeftCell="A'.($inicioTabla + 1).'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetFormatPr defaultRowHeight="16"/>'
            .'<cols>'.$columnas.'</cols>'
            .'<sheetData>'.implode('', $filas).'</sheetData>'
            .'<autoFilter ref="A'.$inicioTabla.':K'.$finTabla.'"/>'
            .'<mergeCells count="2"><mergeCell ref="A1:K1"/><mergeCell ref="A2:K2"/></mergeCells>'
            .'<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
            .'<pageSetup paperSize="9" orientation="landscape" fitToWidth="1" fitToHeight="0"/>'
            .'</worksheet>';
    }

    /** @return list<string> */
    private function celdasDeIndicador(ResultadoIndicador $r, int $fila): array
    {
        $esConteo = $r->unidad !== '%';
        $sinCobertura = $esConteo || $r->codigo === 'I1';

        return [
            $this->texto('A', $r->codigo, self::CODIGO),
            $this->texto('B', $r->nombre, self::TEXTO),
            $this->resultado($r),
            $this->entero('D', $r->numerador),
            $esConteo ? $this->texto('E', 'No corresponde', self::SIN_VALOR) : $this->entero('E', $r->denominador),
            $esConteo ? $this->texto('F', 'No corresponde', self::SIN_VALOR) : $this->entero('F', $r->poblacionElegible),
            $this->entero('G', $r->n),
            match (true) {
                $sinCobertura => $this->texto('H', 'No corresponde', self::SIN_VALOR),
                $r->cobertura === null => $this->texto('H', '—', self::SIN_VALOR),
                default => $this->numero('H', $r->cobertura / 100, self::PORCENTAJE),
            },
            $this->texto('I', $r->etiquetaEstado(), $r->estado === ResultadoIndicador::CALCULADO ? self::CALCULADO : self::PENDIENTE),
            $this->texto('J', $r->fuente, self::TEXTO),
            $this->texto('K', $r->formula, self::TEXTO_LARGO),
        ];
    }

    private function resultado(ResultadoIndicador $r): string
    {
        if ($r->valor === null) {
            return $this->texto('C', $r->etiquetaEstado(), self::SIN_VALOR);
        }

        return $r->unidad === '%'
            ? $this->numero('C', $r->valor / 100, self::PORCENTAJE)
            : $this->numero('C', $r->valor, self::MEJORAS);
    }

    private function entero(string $columna, ?int $valor): string
    {
        return $valor === null ? $this->texto($columna, '—', self::SIN_VALOR) : $this->numero($columna, $valor, self::ENTERO);
    }

    /** La fila se completa en fila(): aquí la referencia lleva un marcador. */
    private function texto(string $columna, string $valor, int $estilo): string
    {
        $seguro = htmlspecialchars($valor, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return "<c r=\"{$columna}#\" s=\"{$estilo}\" t=\"inlineStr\"><is><t xml:space=\"preserve\">{$seguro}</t></is></c>";
    }

    private function numero(string $columna, float|int $valor, int $estilo): string
    {
        return "<c r=\"{$columna}#\" s=\"{$estilo}\"><v>{$valor}</v></c>";
    }

    /** @param  list<string>  $celdas */
    private function fila(int $numero, array $celdas, ?int $alto = null): string
    {
        $altura = $alto === null ? '' : " ht=\"{$alto}\" customHeight=\"1\"";

        return "<row r=\"{$numero}\"{$altura}>".str_replace('#"', $numero.'"', implode('', $celdas)).'</row>';
    }

    private function letra(int $indice): string
    {
        return chr(65 + $indice);
    }

    private function tiposDeContenido(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function relacionesRaiz(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function libro(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Indicadores" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function relacionesLibro(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    /** Colores de la guía visual: marino #172554, texto #15233d, ayuda #536078, verde #087f5b, ámbar #a65d00, borde #dde1da. */
    private function estilos(): string
    {
        $xf = fn (int $fuente, int $relleno, int $borde, string $alineacion, int $formato = 0) => '<xf numFmtId="'.$formato.'" fontId="'.$fuente.'" fillId="'.$relleno.'" borderId="'.$borde.'" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyNumberFormat="1" applyAlignment="1"><alignment '.$alineacion.'/></xf>';
        $centro = 'horizontal="center" vertical="center" wrapText="1"';
        $izquierda = 'horizontal="left" vertical="center" wrapText="1"';

        $formatos = [
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
            $xf(1, 2, 0, 'horizontal="left" vertical="center"'),                 // título
            $xf(2, 0, 0, 'horizontal="left" vertical="center"'),                 // subtítulo
            $xf(3, 0, 0, 'horizontal="left" vertical="center"'),                 // etiqueta
            $xf(0, 0, 0, 'horizontal="left" vertical="center"'),                 // valor
            $xf(4, 2, 1, $centro),                                               // encabezado
            $xf(5, 3, 1, $centro),                                               // código
            $xf(0, 0, 1, $izquierda),                                            // texto
            $xf(2, 0, 1, $izquierda),                                            // texto largo
            $xf(0, 0, 1, $centro, 1),                                            // entero
            $xf(5, 0, 1, $centro, 164),                                          // porcentaje
            $xf(5, 0, 1, $centro, 165),                                          // mejoras
            $xf(6, 0, 1, $centro),                                               // sin valor
            $xf(7, 4, 1, $centro),                                               // calculado
            $xf(8, 5, 1, $centro),                                               // pendiente
            $xf(2, 0, 0, 'horizontal="left" vertical="center"'),                 // nota
        ];

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="2"><numFmt numFmtId="164" formatCode="0.0%"/><numFmt numFmtId="165" formatCode="0&quot; mejoras&quot;"/></numFmts>'
            .'<fonts count="9">'
            .'<font><sz val="11"/><color rgb="FF15233D"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="15"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><sz val="10"/><color rgb="FF536078"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FF172554"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="12"/><color rgb="FF172554"/><name val="Calibri"/></font>'
            .'<font><i/><sz val="10"/><color rgb="FF536078"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FF087F5B"/><name val="Calibri"/></font>'
            .'<font><b/><sz val="10"/><color rgb="FFA65D00"/><name val="Calibri"/></font>'
            .'</fonts>'
            .'<fills count="6">'
            .'<fill><patternFill patternType="none"/></fill>'
            .'<fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FF172554"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFEEF0FF"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFE7F7F0"/></patternFill></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFFFF3D8"/></patternFill></fill>'
            .'</fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>'
            .'<border><left style="thin"><color rgb="FFDDE1DA"/></left><right style="thin"><color rgb="FFDDE1DA"/></right><top style="thin"><color rgb="FFDDE1DA"/></top><bottom style="thin"><color rgb="FFDDE1DA"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($formatos).'">'.implode('', $formatos).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
