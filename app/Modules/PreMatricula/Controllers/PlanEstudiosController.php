<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\ActualizarCursoRequest;
use App\Modules\PreMatricula\Requests\ConfirmarMallaRequest;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Requests\ValidarMallaRequest;
use App\Modules\PreMatricula\Services\PlanEstudios;
use App\Modules\PreMatricula\Services\PrematriculaException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Plan de estudios: consultar, corregir cursos, importar y exportar la malla, como en master. */
final class PlanEstudiosController extends Controller
{
    use RespondeAcciones;

    private const CARPETA = 'pre-matricula/malla';

    private const TIPO_XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    public function __construct(private readonly PlanEstudios $plan) {}

    public function index(CoordinacionRequest $request): View
    {
        try {
            $plan = $this->plan->plan();
        } catch (PrematriculaException $error) {
            return view('modules.pre-matricula.coordinacion.plan', ['plan' => null, 'aviso' => $error->getMessage(), 'cursos' => collect(), 'ciclos' => collect(), 'buscar' => '', 'ciclo' => null]);
        }

        $catalogo = $this->plan->catalogo($plan);
        $buscar = trim((string) $request->validated('buscar'));
        $ciclo = $request->validated('ciclo');
        $cursos = $catalogo
            ->when($ciclo !== null, fn ($cursos) => $cursos->where('ciclo', (int) $ciclo))
            ->when($buscar !== '', fn ($cursos) => $cursos->filter(fn (object $curso): bool => str_contains(mb_strtolower($curso->codigo.' '.$curso->nombre), mb_strtolower($buscar))))
            ->values();

        return view('modules.pre-matricula.coordinacion.plan', [
            'plan' => $plan,
            'cursos' => $cursos,
            'ciclos' => $catalogo->pluck('ciclo')->unique()->sort()->values(),
            'buscar' => $buscar,
            'ciclo' => $ciclo,
        ]);
    }

    public function edit(CoordinacionRequest $request, string $codigo): View
    {
        $curso = $this->plan->catalogo($this->plan->plan())->get($codigo) ?? abort(404);

        return view('modules.pre-matricula.coordinacion.curso', ['curso' => $curso]);
    }

    public function update(ActualizarCursoRequest $request, string $codigo): RedirectResponse
    {
        try {
            $this->plan->actualizarCurso($codigo, $request->datos());
        } catch (PrematriculaException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('pre-matricula.coordinacion.plan')->with('status', "Curso {$codigo} actualizado. Las prematrículas que lo incluyen se revalidaron.");
    }

    public function plantilla(CoordinacionRequest $request): Response
    {
        return response($this->plan->excel(), 200, [
            'Content-Type' => self::TIPO_XLSX,
            'Content-Disposition' => 'attachment; filename="plantilla-malla.xlsx"',
        ]);
    }

    public function exportar(CoordinacionRequest $request): Response
    {
        $plan = $this->plan->plan();

        return response($this->plan->excel($this->plan->catalogo($plan)), 200, [
            'Content-Type' => self::TIPO_XLSX,
            'Content-Disposition' => 'attachment; filename="malla-'.$plan->codigo.'.xlsx"',
        ]);
    }

    public function validar(ValidarMallaRequest $request): RedirectResponse
    {
        $archivo = $request->file('archivo');
        $sha256 = hash_file('sha256', $archivo->getRealPath());
        Storage::disk('local')->putFileAs(self::CARPETA, $archivo, $sha256.'.xlsx');

        try {
            $plan = $this->plan->plan();
            $reporte = $this->plan->validar($this->plan->leer($archivo->getRealPath()), $this->plan->catalogo($plan));
        } catch (PrematriculaException $error) {
            return back()->with('error', $error->getMessage());
        }

        return redirect()->route('pre-matricula.coordinacion.plan')->with('reporte_malla', [
            'valido' => $reporte['valido'],
            'huella' => $reporte['huella'],
            'resumen' => $reporte['resumen'],
            'total_errores' => count($reporte['errores']),
            'errores' => array_slice($reporte['errores'], 0, 100),
            'cambios' => array_slice(array_values(array_filter($reporte['cambios'], fn (array $cambio): bool => $cambio['accion'] !== 'sin_cambios')), 0, 100),
            'sha256' => $sha256,
            'archivo' => $archivo->getClientOriginalName(),
        ]);
    }

    public function confirmar(ConfirmarMallaRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $ruta = self::CARPETA.'/'.$datos['sha256'].'.xlsx';
        if (! Storage::disk('local')->exists($ruta)) {
            return redirect()->route('pre-matricula.coordinacion.plan')->with('error', 'El archivo validado ya no está disponible. Vuelve a subirlo.');
        }

        try {
            $resumen = $this->plan->importar($this->plan->leer(Storage::disk('local')->path($ruta)), $datos['huella']);
        } catch (PrematriculaException $error) {
            return redirect()->route('pre-matricula.coordinacion.plan')->with('error', $error->getMessage());
        }
        Storage::disk('local')->delete($ruta);

        return redirect()->route('pre-matricula.coordinacion.plan')
            ->with('status', "Malla importada: {$resumen['crear']} cursos nuevos, {$resumen['actualizar']} actualizados y {$resumen['sin_cambios']} sin cambios.");
    }
}
