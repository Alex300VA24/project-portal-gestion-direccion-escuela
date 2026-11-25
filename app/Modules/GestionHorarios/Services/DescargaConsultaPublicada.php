<?php

namespace App\Modules\GestionHorarios\Services;

use Illuminate\Http\Response;

/** Exporta exclusivamente los planos que la consulta autorizada acaba de preparar. */
final class DescargaConsultaPublicada
{
    public static function pdf(array $planos, object $periodo, string $estado = 'PUBLICADO'): Response
    {
        abort_if(!$planos, 422, 'No hay clases publicadas para los filtros seleccionados.');
        abort_unless(class_exists(\Dompdf\Dompdf::class), 503, 'La descarga PDF no está disponible.');
        $logo = public_path('images/logoInfo.png');
        if (!is_file($logo)) $logo = public_path('img/logo-informatica.png');
        try {
            $contenido = (new PdfAcademicoA4)->generar($planos, [
                'periodoSeleccionado'=>$periodo, 'seleccion'=>(object)['estado'=>$estado],
                'bloquesPeriodo'=>collect(ConsultaPublicadaPresentacion::bloquesVisuales()),
            ], is_file($logo) ? $logo : null);
        } catch (\Throwable $e) {
            report($e);
            abort(422, 'No se pudo preparar un PDF legible para esta selección. Consulte otro alcance o comuníquese con soporte.');
        }
        $codigo = preg_replace('/[^A-Za-z0-9_-]/', '-', (string)$periodo->codigo);
        return response($contenido, 200, ['Content-Type'=>'application/pdf',
            'Content-Disposition'=>'attachment; filename="horario-publicado-'.$codigo.'.pdf"',
            'Cache-Control'=>'private, no-store', 'X-Content-Type-Options'=>'nosniff']);
    }
}
