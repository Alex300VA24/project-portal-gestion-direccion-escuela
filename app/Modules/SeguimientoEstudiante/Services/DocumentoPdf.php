<?php

namespace App\Modules\SeguimientoEstudiante\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

final class DocumentoPdf
{
    /** Renderiza la proyección autorizada que recibe del controlador. */
    public function render(string $vista, array $datos): string
    {
        $images = app_path('Modules/SeguimientoEstudiante/Resources/images');
        $crestPath = $images.'/escudo-unt-documento.jpg';
        $options = new Options([
            'chroot' => $images,
            'defaultFont' => 'DejaVu Sans',
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
            'isRemoteEnabled' => false,
        ]);

        $pdf = new Dompdf($options);
        $pdf->setPaper('A4');
        $pdf->loadHtml(view($vista, $datos + compact('crestPath'))->render());
        $pdf->render();

        $font = $pdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $pdf->getCanvas()->page_text(444, 809, 'Página {PAGE_NUM} de {PAGE_COUNT}', $font, 8, [.35, .39, .44]);

        return $pdf->output();
    }

    public function descargar(string $vista, array $datos, string $nombre): Response
    {
        return response($this->render($vista, $datos), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
