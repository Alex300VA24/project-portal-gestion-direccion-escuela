<?php

namespace App\Modules\SeguimientoEstudiante\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEstudiante\Requests\RecepcionSecretariaRequest;
use App\Modules\SeguimientoEstudiante\Services\Secretaria\LotesSecretaria;
use App\Modules\SeguimientoEstudiante\Services\Secretaria\PrevalidarRecepcion;
use App\Modules\SeguimientoEstudiante\Services\SeguimientoEstudianteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RecepcionSecretariaController extends Controller
{
    public function index(Request $request, SeguimientoEstudianteService $seguimiento): View
    {
        $seguimiento->personaVinculada($request->user());

        return view('modules.seguimiento-estudiante.recepcion-secretaria');
    }

    public function plantilla(Request $request, SeguimientoEstudianteService $seguimiento, PrevalidarRecepcion $servicio, string $tipo): StreamedResponse
    {
        $seguimiento->personaVinculada($request->user());
        $campos = $servicio->encabezados($tipo);

        return response()->streamDownload(static function () use ($campos): void {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $campos, ',', '"', '');
            fclose($salida);
        }, 'plantilla-'.$tipo.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    public function prevalidar(RecepcionSecretariaRequest $request, PrevalidarRecepcion $servicio): View
    {
        $datos = $request->validated();

        $resultado = $servicio->revisar($datos['tipo'], $datos['archivo']->get());

        return view('modules.seguimiento-estudiante.recepcion-secretaria', compact('resultado'));
    }

    public function lotes(Request $request, SeguimientoEstudianteService $seguimiento, LotesSecretaria $lotes, ?string $lote = null, ?string $revision = null): View
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $id = $this->id($lote);
        $version = $this->id($revision);
        abort_if($version !== null && $version > 2147483647, 404);

        return view('modules.seguimiento-estudiante.lotes-secretaria', $lotes->consultar($persona, $id, $version));
    }

    public function guardarLote(RecepcionSecretariaRequest $request, LotesSecretaria $lotes): RedirectResponse
    {
        $persona = $request->persona();
        $datos = $request->validated();
        [$escuela, $periodo] = explode('-', $datos['ambito']);
        $datos['id_escuela'] = $this->id($escuela);
        $datos['id_periodo'] = $this->id($periodo);
        [$lote, $revision] = $lotes->guardar($persona, $request->user()->getKey(), $datos, $datos['archivo']->get());

        return redirect()->route('seguimiento-estudiante.secretaria.lotes.mostrar', [$lote, $revision]);
    }

    public function confirmarLote(Request $request, SeguimientoEstudianteService $seguimiento, LotesSecretaria $lotes, string $lote, string $revision): RedirectResponse
    {
        $persona = $seguimiento->personaVinculada($request->user());
        $id = $this->id($lote);
        $version = $this->id($revision);
        abort_if($version > 2147483647, 404);
        $lotes->confirmar($persona, $request->user()->getKey(), $id, $version);

        return redirect()->route('seguimiento-estudiante.secretaria.lotes.mostrar', [$id, $version]);
    }

    private function id(?string $valor): ?int
    {
        if ($valor === null) {
            return null;
        }

        $id = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        abort_if($id === false, 404);

        return $id;
    }
}
