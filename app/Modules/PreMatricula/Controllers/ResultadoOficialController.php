<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Models\ImportacionHistorial;
use App\Modules\PreMatricula\Requests\ConfirmarResultadosRequest;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Requests\ValidarResultadosRequest;
use App\Modules\PreMatricula\Services\IdentidadAcademica;
use App\Modules\PreMatricula\Services\PrematriculaException;
use App\Modules\PreMatricula\Services\ResultadosOficiales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Importación de resultados oficiales en dos pasos: validar y confirmar. */
final class ResultadoOficialController extends Controller
{
    private const CARPETA = 'pre-matricula/resultados';

    public function __construct(private readonly ResultadosOficiales $resultados) {}

    public function index(CoordinacionRequest $request): View
    {
        return view('modules.pre-matricula.coordinacion.resultados', [
            'importaciones' => ImportacionHistorial::query()->with('autor')->orderByDesc('id_importacion')->limit(15)->get(),
            'reporte' => session('reporte'),
        ]);
    }

    public function plantilla(CoordinacionRequest $request): Response
    {
        return response($this->resultados->plantilla(), 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="plantilla-resultados-oficiales.xlsx"',
        ]);
    }

    public function validar(ValidarResultadosRequest $request): RedirectResponse
    {
        $archivo = $request->file('archivo');
        $sha256 = hash_file('sha256', $archivo->getRealPath());
        Storage::disk('local')->putFileAs(self::CARPETA, $archivo, $sha256.'.xlsx');

        try {
            $reporte = $this->resultados->validar($this->resultados->leer($archivo->getRealPath()));
        } catch (PrematriculaException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }
        unset($reporte['preparadas']);
        $reporte['total_errores'] = count($reporte['errores']);
        $reporte['errores'] = array_slice($reporte['errores'], 0, 100);
        $reporte['cambios'] = array_slice($reporte['cambios'], 0, 100);

        return redirect()->route('pre-matricula.coordinacion.resultados')
            ->withInput()
            ->with('reporte', [...$reporte, 'sha256' => $sha256, 'archivo' => $archivo->getClientOriginalName(), 'motivo' => $request->validated('motivo')]);
    }

    public function confirmar(ConfirmarResultadosRequest $request, IdentidadAcademica $identidad): RedirectResponse
    {
        $datos = $request->validated();
        $ruta = self::CARPETA.'/'.$datos['sha256'].'.xlsx';
        if (! Storage::disk('local')->exists($ruta)) {
            return redirect()->route('pre-matricula.coordinacion.resultados')->with('error', 'El archivo validado ya no está disponible. Vuelve a subirlo.');
        }

        try {
            $importacion = $this->resultados->importar(
                $this->resultados->leer(Storage::disk('local')->path($ruta)),
                $datos['huella'], $datos['sha256'], $datos['motivo'], $identidad->idUsuarioObligatorio($request->user()),
            );
        } catch (PrematriculaException $error) {
            return redirect()->route('pre-matricula.coordinacion.resultados')->with('error', $error->getMessage());
        }
        Storage::disk('local')->delete($ruta);

        $r = $importacion->resumen;

        return redirect()->route('pre-matricula.coordinacion.resultados')
            ->with('status', "Importación registrada: {$r['crear']} nuevos, {$r['actualizar']} actualizados y {$r['sin_cambios']} sin cambios. Las prematrículas abiertas se revalidaron.");
    }
}
