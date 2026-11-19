<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Shared\Models\Core\Seccion;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

/**
 * Genera el PDF del reporte de una sección a partir de los datos ya calculados
 * por ReporteService. No crea registros en comun.archivo y no usa el gateway
 * de archivos.
 */
final class ReportePdfService
{
    public function generar(
        object $reporte,
        Seccion $seccion,
        ?string $escuela,
        ?string $linea,
        string $generadoEn,
    ): string {
        $opciones = new Options();
        $opciones->setDefaultFont('DejaVu Sans');
        $opciones->setIsRemoteEnabled(false);
        $opciones->setIsJavascriptEnabled(false);

        $directorioTemporal = storage_path('app/dompdf');
        File::ensureDirectoryExists($directorioTemporal);
        $opciones->setTempDir($directorioTemporal);

        $dompdf = new Dompdf($opciones);
        $dompdf->loadHtml(view('modules.investigacion-formativa.director.reporte-pdf', [
            'reporte' => $reporte,
            'seccion' => $seccion,
            'escuela' => $escuela,
            'linea' => $linea,
            'generadoEn' => $generadoEn,
        ])->render(), 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
