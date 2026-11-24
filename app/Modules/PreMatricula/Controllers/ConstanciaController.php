<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Models\RevisionSolicitud;
use App\Modules\PreMatricula\Policies\PrematriculaPolicy;
use App\Modules\PreMatricula\Services\IdentidadAcademica;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Descarga de la constancia PDF guardada en un envío. */
final class ConstanciaController extends Controller
{
    public function __invoke(Request $request, int $revision, PrematriculaPolicy $policy, IdentidadAcademica $identidad): Response
    {
        $registro = RevisionSolicitud::query()->with('solicitud')->whereNotNull('numero_revision')->find($revision);
        abort_if($registro === null || $registro->pdf_base64 === null, 404);
        abort_unless($policy->verSolicitud($request->user(), $registro->solicitud, $identidad->estudiante($request->user())), 404);

        $nombre = 'constancia-prematricula-'.$registro->snapshot['periodo']['codigo'].'-'.$registro->snapshot['estudiante']['codigo_universitario'].'-v'.$registro->numero_revision.'.pdf';

        return response(base64_decode($registro->pdf_base64), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
