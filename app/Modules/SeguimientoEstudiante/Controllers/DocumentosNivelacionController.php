<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Services\DocumentoPdf;
use App\Modules\SeguimientoEstudiante\Services\Nivelacion\DocumentosNivelacionService;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class DocumentosNivelacionController extends Controller
{
    private const HEADERS = [
        'Content-Type' => 'application/pdf',
        'Cache-Control' => 'private, no-store',
        'X-Content-Type-Options' => 'nosniff',
    ];

    public function index(Request $request, SeguimientoEstudianteService $seguimiento, DocumentosNivelacionService $documentos): View
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $programas = $documentos->listado($persona);

        return view('modules.seguimiento-estudiante.nivelacion.documentos', compact('programas'));
    }

    public function resumen(Request $request, SeguimientoEstudianteService $seguimiento, DocumentosNivelacionService $documentos, DocumentoPdf $pdf, string $inscripcion): Response
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $datos = $documentos->resumen($persona, $this->id($inscripcion));

        return $pdf->descargar('modules.seguimiento-estudiante.nivelacion.pdf', $datos + ['tipo' => 'personal'], 'nivelacion-personal.pdf');
    }

    public function gestion(Request $request, SeguimientoEstudianteService $seguimiento, DocumentosNivelacionService $documentos, DocumentoPdf $pdf, string $programa, string $tipo): Response
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $datos = $documentos->ficha($persona, $this->id($programa), $tipo);

        return $pdf->descargar('modules.seguimiento-estudiante.nivelacion.pdf', $datos + compact('tipo'), 'nivelacion-'.$tipo.'.pdf');
    }

    public function material(Request $request, SeguimientoEstudianteService $seguimiento, DocumentosNivelacionService $documentos, string $inscripcion, string $archivo): BinaryFileResponse
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $ruta = $documentos->materiales($persona, [$this->id($inscripcion)])
            ->where('material.id_archivo', $this->id($archivo))->value('archivo.ruta_almacenamiento');

        return $this->descargarMaterial($ruta);
    }

    public function materialEnGestion(Request $request, SeguimientoEstudianteService $seguimiento, DocumentosNivelacionService $documentos, string $programa, string $archivo): BinaryFileResponse
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $ruta = $documentos->materialEnGestion($persona, $this->id($programa), $this->id($archivo))
            ->value('archivo.ruta_almacenamiento');

        return $this->descargarMaterial($ruta);
    }

    public function publicarMaterial(Request $request, SeguimientoEstudianteService $seguimiento, DocumentosNivelacionService $documentos, string $programa, string $archivo): RedirectResponse
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $request->validate(['visible' => ['required', 'boolean']]);
        $documentos->publicarMaterial($persona, $this->id($programa), $this->id($archivo), $request->boolean('visible'));

        return redirect()->route('seguimiento-estudiante.nivelacion.documentos')->with('status', 'Visibilidad del material actualizada.');
    }

    private function descargarMaterial(?string $ruta): BinaryFileResponse
    {
        abort_unless(is_string($ruta) && preg_match('#\Aseguimiento-estudiante/nivelacion/[\w-]+\.pdf\z#iD', $ruta), 404);

        $disco = Storage::disk('local');
        $base = realpath($disco->path('seguimiento-estudiante/nivelacion'));
        $archivoLocal = realpath($disco->path($ruta));
        abort_unless(is_string($base) && is_string($archivoLocal)
            && str_starts_with($archivoLocal, $base.DIRECTORY_SEPARATOR) && is_file($archivoLocal)
            && is_readable($archivoLocal), 404);

        return response()->download($archivoLocal, 'material-nivelacion.pdf', self::HEADERS);
    }

    public function adjuntar(Request $request, SeguimientoEstudianteService $seguimiento, DocumentosNivelacionService $documentos, string $programa): RedirectResponse
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $datos = $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf', 'max:5120'],
            'titulo' => ['required', 'string', 'max:150'],
            'visible' => ['sometimes', 'boolean'],
        ]);
        $documentos->adjuntar($persona, $this->id($programa), $datos['archivo'], $datos['titulo'], $request->boolean('visible'));

        return redirect()->route('seguimiento-estudiante.nivelacion.documentos')->with('status', 'Material de Nivelación guardado.');
    }

    private function id(string $valor): int
    {
        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return $id;
    }
}
