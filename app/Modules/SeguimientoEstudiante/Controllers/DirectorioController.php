<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEstudiante\Requests\DirectorioRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class DirectorioController extends Controller
{
    public function __construct(private readonly ExpedientePolicy $policy) {}

    private function estudiantes(DirectorioRequest $request): Builder
    {
        $buscar = trim($request->validated('q') ?? '');
        $consulta = $this->policy->visibles($request->user(), $request->persona())
            ->join('core.persona as persona', 'persona.id_persona', '=', 'core.estudiante.id_persona')
            ->select('core.estudiante.id_estudiante', 'codigo_universitario', 'nombres', 'apellido_paterno', 'apellido_materno')
            ->orderBy('codigo_universitario')->orderBy('core.estudiante.id_estudiante');

        if ($buscar !== '') {
            $consulta->whereRaw(
                "strpos(lower(concat_ws(' ', codigo_universitario, nombres, apellido_paterno, apellido_materno)), lower(?)) > 0",
                [$buscar],
            );
        }

        return $consulta;
    }

    public function index(DirectorioRequest $request): Response
    {
        $estudiantes = $this->estudiantes($request)->paginate(25)->withQueryString();

        return response()->view('modules.seguimiento-estudiante.index', compact('estudiantes'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function exportar(DirectorioRequest $request): StreamedResponse
    {
        $consulta = $this->estudiantes($request);

        return response()->streamDownload(static function () use ($consulta): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Código universitario', 'Nombres', 'Apellido paterno', 'Apellido materno'], ',', '"', '');
            foreach ($consulta->cursor() as $fila) {
                $campos = [$fila->codigo_universitario, $fila->nombres, $fila->apellido_paterno, $fila->apellido_materno];
                foreach ($campos as &$campo) {
                    if (preg_match('/^[\x00-\x20]*[=+@-]/u', (string) $campo)) {
                        $campo = "'".$campo;
                    }
                }
                unset($campo);
                fputcsv($salida, $campos, ',', '"', '');
            }
            fclose($salida);
        }, 'expedientes.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }
}
