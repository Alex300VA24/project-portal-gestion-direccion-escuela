<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Services\ArchivoService;
use App\Modules\TesisProyectoTesis\Services\ExpedienteService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descarga de archivos del módulo: nunca por URL pública, siempre con la Policy del proyecto.
 */
final class ArchivoController extends BaseController
{
    public function descargar(Request $request, string $tipo, string $uuid, ExpedienteService $expediente, ArchivoService $archivos): StreamedResponse
    {
        [$proyecto, $idArchivo] = $expediente->ubicarArchivo($tipo, $uuid);

        abort_if($proyecto === null || $idArchivo === null, 404);
        abort_unless($request->user()->can('ver', $proyecto), 403);

        return $archivos->descargar($idArchivo);
    }
}
