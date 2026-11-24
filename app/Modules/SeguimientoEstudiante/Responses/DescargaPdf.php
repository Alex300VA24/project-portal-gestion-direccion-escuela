<?php

namespace App\Modules\SeguimientoEstudiante\Responses;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;

final class DescargaPdf
{
    public const HEADERS = [
        'Content-Type' => 'application/pdf',
        'Cache-Control' => 'private, no-store',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function generar(string $vista, array $datos, string $nombre): Response
    {
        return $this->adjuntar($this->render($vista, $datos), $nombre);
    }

    public function adjuntar(string $contenido, string $nombre): Response
    {
        return response($contenido, 200, self::HEADERS + [
            'Content-Disposition' => 'attachment; filename="'.$nombre.'"',
        ]);
    }

    /** Renderiza la proyección autorizada que recibe del controlador. */
    private function render(string $vista, array $datos): string
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
}
