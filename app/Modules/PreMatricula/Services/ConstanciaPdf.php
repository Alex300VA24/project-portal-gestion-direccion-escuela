<?php

namespace App\Modules\PreMatricula\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/** Genera la constancia de prematrícula (A4) a partir del snapshot de un envío. */
final class ConstanciaPdf
{
    /** @param array<string, mixed> $snapshot */
    public function generar(array $snapshot): string
    {
        $opciones = new Options;
        $opciones->set('isRemoteEnabled', false);
        $opciones->set('defaultFont', 'DejaVu Sans');
        $opciones->setChroot(public_path());

        $pdf = new Dompdf($opciones);
        $pdf->loadHtml(view('modules.pre-matricula.pdf.constancia', [
            'snapshot' => $snapshot,
            'escudo' => is_file(public_path('img/logo-informatica.png')) ? public_path('img/logo-informatica.png') : null,
        ])->render(), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }
}
