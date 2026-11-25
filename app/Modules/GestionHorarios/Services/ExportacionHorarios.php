<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Repositories\ConsultaPeriodosRepository;
use Dompdf\Dompdf;
use Illuminate\Database\ConnectionInterface;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

/**
 * Exportación de horarios persistidos. No modifica sesiones ni permisos.
 * La autorización corresponde al componente Livewire que invoca el servicio.
 */
final class ExportacionHorarios
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(int $periodo, int $horario, string $ciclo = '', string $seccion = ''): array
    {
        $datos = (new ConsultaHorarios($this->db))->consultar($periodo, $horario, $ciclo, $seccion);
        if (! $datos['seleccion']) {
            throw new RuntimeException('El horario no pertenece al periodo seleccionado.');
        }
        $datos['periodoSeleccionado'] = $datos['periodos']->firstWhere('id_periodo', $periodo);
        $datos['bloquesPeriodo'] = (new ConsultaPeriodosRepository($this->db))->bloques($periodo);

        return $datos;
    }

    private function preparar(array $datos): array
    {
        if ($datos['sesiones']->isEmpty()) {
            throw new RuntimeException('No hay actividades para exportar.');
        }
        $docentes = [];
        foreach ($datos['docentes'] as $actividad => $asignaciones) {
            $docentes[(string) $actividad] = $asignaciones->map(
                fn ($d) => trim(implode(' ', array_filter([
                    $d->nombres, $d->apellido_paterno, $d->apellido_materno,
                ], fn ($v) => $v !== null && $v !== '')))
            )->unique()->values()->all();
        }

        return (new DistribucionHoraria)->preparar(
            $datos['sesiones']->all(),
            $docentes,
            ($datos['bloquesPeriodo'] ?? collect())->all(),
            $datos['alcanceExportacion'] ?? '', (bool)($datos['exportacionAcademicaQa'] ?? false)
        );
    }

    public function pdf(array $datos): string
    {
        $datos['gruposExportacion'] = $this->preparar($datos);
        $logo = public_path('images/logoInfo.png');
        if (! is_file($logo)) {
            $logo = public_path('img/logo-informatica.png');
        }
        if ($datos['exportacionAcademicaQa'] ?? false) {
            return (new PdfAcademicoA4)->generar($datos['gruposExportacion'], $datos, is_file($logo) ? $logo : null);
        }
        $datos['logoInstitucional'] = is_file($logo)
            ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($logo))
            : null;
        $dompdf = new Dompdf;
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->loadHtml(view(($datos['exportacionAcademicaQa'] ?? false) ? 'modules.gestion-horarios.exportacion-academica-qa-pdf' : 'modules.gestion-horarios.exportacion-pdf', $datos)->render(), 'UTF-8');
        $dompdf->render();

        return $dompdf->output();
    }

    public function xlsx(array $datos): string
    {
        $grupos = $this->preparar($datos);
        if ($datos['exportacionAcademicaQa'] ?? false) {
            return (new XlsxAcademicoA4)->generar($grupos, $datos);
        }
        $libro = new Spreadsheet;
        try {
            foreach ($grupos as $numero => $grupo) {
                $hoja = $numero === 0 ? $libro->getActiveSheet() : $libro->createSheet();
                $nombre = preg_replace('/[\\\\\/*?:\[\]]/', '-', ($grupo['alcance'] ?? '') !== '' ? 'Horario seleccionado' : 'P'.$grupo['plan'].'-C'.$grupo['ciclo'].'-'.$grupo['seccion']);
                $hoja->setTitle(mb_substr($nombre, 0, 23).'-'.($numero + 1));
                $hoja->setShowGridLines(false);
                $dias = $grupo['dias'];
                $ultima = Coordinate::stringFromColumnIndex(count($dias) + 1);

                $encabezados = [
                    'UNIVERSIDAD NACIONAL DE TRUJILLO',
                    (string) $grupo['facultad'],
                    (string) $grupo['escuela'],
                    'HORARIO ACADÉMICO · PERIODO '.($datos['periodoSeleccionado']->codigo ?? 'sin especificar').' · '.(($grupo['alcance'] ?? '') ?: 'CICLO '.$grupo['ciclo'].' · SECCIÓN '.$grupo['seccion']),
                    'Plan curricular: '.$grupo['plan_nombre'].' · Estado: '.($datos['seleccion']->estado ?? 'No registrado'),
                    ($datos['exportacionAcademicaQa'] ?? false) ? 'Franjas según la versión guardada.' : 'Franjas según sesiones y bloques del periodo. Receso académico: no confirmado.',
                ];
                foreach ($encabezados as $fila => $texto) {
                    $r = $fila + 1;
                    $hoja->mergeCells('A'.$r.':'.$ultima.$r);
                    $this->texto($hoja, 1, $r, $texto);
                    $hoja->getRowDimension($r)->setRowHeight($fila === 3 ? 36 : 22);
                }
                $hoja->getStyle('A1')->getFont()->setBold(true)->setSize(13);
                $hoja->getStyle('A4:'.$ultima.'4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF193D5B');
                $hoja->getStyle('A4:'.$ultima.'4')->getFont()->getColor()->setARGB('FFFFFFFF');
                $hoja->getStyle('A4:'.$ultima.'4')->getFont()->setBold(true);

                $this->texto($hoja, 1, 7, 'HORA');
                foreach ($dias as $i => $dia) {
                    $this->texto($hoja, $i + 2, 7, $dia);
                }
                $hoja->getStyle('A7:'.$ultima.'7')->getFont()->setBold(true);
                $hoja->getStyle('A7:'.$ultima.'7')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF193D5B');
                $hoja->getStyle('A7:'.$ultima.'7')->getFont()->getColor()->setARGB('FFFFFFFF');
                $hoja->getRowDimension(7)->setRowHeight(24);

                $r = 8;
                $filasQa = ($datos['exportacionAcademicaQa'] ?? false) ? array_column(DistribucionHoraria::filasAcademicas($grupo, ($datos['bloquesPeriodo'] ?? collect())->all()), null, 'inicio') : [];
                for ($hora = $grupo['desde']; $hora < $grupo['hasta']; $hora += 60) {
                    if ($filasQa[$hora]['receso'] ?? false) {
                        $this->texto($hoja, 1, $r, DistribucionHoraria::rango12($hora,$hora+60));
                        $hoja->mergeCells('B'.$r.':'.$ultima.$r);
                        $this->texto($hoja, 2, $r, 'RECESO');
                        $hoja->getStyle('A'.$r.':'.$ultima.$r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFDFE6EB');
                        $hoja->getRowDimension($r++)->setRowHeight(22);
                        continue;
                    }
                    $activas = [];
                    $maximo = 1;
                    foreach ($dias as $dia) {
                        $sesiones = array_values(array_filter($grupo['sesiones'], fn ($s) => $s['dia'] === $dia
                            && $s['inicio'] < $hora + 60 && $s['fin'] > $hora));
                        usort($sesiones, fn ($a, $b) => [$a['inicio'], $a['codigo'], $a['id']] <=> [$b['inicio'], $b['codigo'], $b['id']]);
                        $activas[$dia] = $sesiones;
                        $maximo = max($maximo, count($sesiones));
                    }
                    if ($maximo > 1) {
                        $hoja->mergeCells('A'.$r.':A'.($r + $maximo - 1));
                    }
                    $this->texto($hoja, 1, $r, DistribucionHoraria::rango12($hora,$hora+60));
                    $hoja->getStyle('A'.$r)->getFont()->setBold(true);
                    for ($subfila = 0; $subfila < $maximo; $subfila++) {
                        $hoja->getRowDimension($r + $subfila)->setRowHeight($maximo > 1 ? 51 : (array_filter($activas) ? 49 : 21));
                        foreach ($dias as $i => $dia) {
                            if (isset($activas[$dia][$subfila])) {
                                $s = $activas[$dia][$subfila];
                                $tipo = $s['tipo'].($s['grupo'] !== '' ? ' · Grupo '.$s['grupo'] : '');
                                $valor = $s['codigo'].' · '.$s['curso']."\n".$tipo.' · '.$s['horario']."\n".$s['ambiente']."\n".$s['docente'].(($grupo['alcance'] ?? '') ? "\n".$s['alcance_sesion'] : '');
                                $this->texto($hoja, $i + 2, $r + $subfila, $valor);
                                $celda = Coordinate::stringFromColumnIndex($i + 2).($r + $subfila);
                                $hoja->getStyle($celda)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF'.$s['color']);
                                $hoja->getStyle($celda)->getFont()->setSize(8);
                                if ($datos['exportacionAcademicaQa'] ?? false) {
                                    $lineas = array_sum(array_map(fn ($t) => max(1,(int)ceil(mb_strlen($t)/26)), explode("\n",$valor)));
                                    $dim = $hoja->getRowDimension($r+$subfila); $dim->setRowHeight(max($dim->getRowHeight(), 8+12*$lineas));
                                }
                            } else {
                                $this->texto($hoja, $i + 2, $r + $subfila, '');
                            }
                        }
                    }
                    $r += $maximo;
                }
                $finCuadricula = $r - 1;
                $hoja->getStyle('A7:'.$ultima.$finCuadricula)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $hoja->getStyle('A1:'.$ultima.$finCuadricula)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $hoja->freezePane('B8');
                $hoja->getColumnDimension('A')->setWidth(19);
                foreach ($dias as $i => $dia) {
                    $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i + 2))->setWidth(count($dias) > 5 ? 29 : 34);
                }

                // Estas secciones quedan disponibles en el XLSX para consulta,
                // pero no reducen el tamaño de letra del horario impreso.
                $r += 2;
                $this->texto($hoja, 1, $r++, 'DETALLE COMPLETO DE SESIONES (CONSULTA EN EXCEL; NO SE IMPRIME)');
                foreach (['Día', 'Horario', 'Código / curso', 'Tipo / grupo', 'Docente', 'Ambiente'] as $i => $titulo) {
                    $this->texto($hoja, $i + 1, $r, $titulo);
                }
                $hoja->getStyle('A'.$r.':F'.$r)->getFont()->setBold(true);
                $r++;
                $catalogo = [];
                foreach ($grupo['sesiones'] as $s) {
                    $datosFila = [
                        $s['dia'], $s['horario'], $s['codigo'].' · '.$s['curso'],
                        $s['tipo'].($s['grupo'] ? ' · '.$s['grupo'] : '').(($grupo['alcance'] ?? '') ? ' · '.$s['alcance_sesion'] : ''),
                        $s['docente'], $s['ambiente'],
                    ];
                    foreach ($datosFila as $i => $valor) {
                        $this->texto($hoja, $i + 1, $r, $valor);
                    }
                    $hoja->getRowDimension($r++)->setRowHeight(27);
                    $catalogo[json_encode([$s['codigo'], $s['curso'], $s['docente']], JSON_THROW_ON_ERROR)]
                        = [$s['codigo'], $s['curso'], $s['docente']];
                }
                $r += 2;
                $this->texto($hoja, 1, $r++, 'RELACIÓN DE ASIGNATURAS Y DOCENTES');
                foreach ($catalogo as $registro) {
                    foreach ($registro as $i => $valor) {
                        $this->texto($hoja, $i + 1, $r, $valor);
                    }
                    $hoja->getRowDimension($r++)->setRowHeight(27);
                }
                $hoja->getStyle('A1:F'.$r)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER);
                $hoja->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToPage(true)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd(1, 7);
                $hoja->getPageSetup()->setPrintArea('A1:'.$ultima.$finCuadricula);
                $hoja->getPageMargins()->setLeft(.2)->setRight(.2)->setTop(.3)->setBottom(.3);
                $hoja->getHeaderFooter()->setOddFooter('&CUNT · '.($datos['periodoSeleccionado']->codigo ?? '').' · '.(($grupo['alcance'] ?? '') ?: 'Ciclo '.$grupo['ciclo'].' · '.$grupo['seccion']));
            }
            ob_start();
            try {
                (new Xlsx($libro))->save('php://output');

                return (string) ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }
        } finally {
            $libro->disconnectWorksheets();
        }
    }

    private function texto($hoja, int $columna, int $fila, string $contenido): void
    {
        $hoja->getCell(Coordinate::stringFromColumnIndex($columna).$fila)
            ->setValueExplicit($contenido, DataType::TYPE_STRING);
    }
}
