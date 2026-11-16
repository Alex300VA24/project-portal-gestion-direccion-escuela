<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Requests\RegistrarResolucionRequest;
use App\Modules\TesisProyectoTesis\Requests\SubirActaFirmadaRequest;
use App\Modules\TesisProyectoTesis\Services\ActaService;
use App\Modules\TesisProyectoTesis\Services\DesignacionService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class SecretariaController extends BaseController
{
    public function resoluciones(Request $request, SeguimientoService $seguimiento, IdentidadService $identidad, DesignacionService $designacion): View
    {
        $this->identidad($request);

        $pendientes = $designacion->solicitudesPendientes();
        $actual = $pendientes->firstWhere('uuid', (string) $request->query('solicitud', '')) ?? $pendientes->first();

        return $this->vista($request, 'secretaria.resoluciones', [
            'pendientes' => $pendientes,
            'actual' => $actual,
            'ficha' => $actual ? $seguimiento->ficha($actual->proyecto) : null,
            'sugerido' => $actual?->id_docente_sugerido ? ($identidad->docentes([$actual->id_docente_sugerido])[$actual->id_docente_sugerido] ?? null) : null,
            'docentes' => $identidad->docentesActivos(),
            'recientes' => $designacion->resolucionesRecientes(),
        ], 'secretaria', 'resoluciones', ['resoluciones' => $pendientes->count()]);
    }

    public function actas(Request $request, ActaService $actas): View
    {
        $this->identidad($request);
        $lista = $actas->listado();

        return $this->vista($request, 'secretaria.actas', ['actas' => $lista], 'secretaria', 'actas',
            ['actas' => $lista->whereNull('id_archivo_firmado')->count()]);
    }

    public function verActa(Request $request, Acta $acta, ActaService $actas): View
    {
        $this->identidad($request);
        abort_unless($request->user()->can('gestionarComoSecretaria', $acta->proyecto), 403);

        return $this->vista($request, 'secretaria.acta', $actas->documento($acta), 'secretaria', 'actas');
    }

    public function subirFirmada(SubirActaFirmadaRequest $request, Acta $acta, ActaService $actas): RedirectResponse
    {
        $actas->subirFirmada($acta, $this->identidad($request), $request->file('archivo'));

        return redirect()->route('tesis-proyecto-tesis.secretaria.actas.ver', $acta)->with('status', 'Documento firmado registrado. Ahora puedes informar al Director.');
    }

    public function informar(Request $request, Acta $acta, ActaService $actas): RedirectResponse
    {
        abort_unless($request->user()->can('gestionarComoSecretaria', $acta->proyecto), 403);
        $actas->informarDirector($acta, $this->identidad($request));

        return redirect()->route('tesis-proyecto-tesis.secretaria.actas')->with('status', "Se informó al Director (N.° {$acta->numero}).");
    }

    public function registrarResolucion(RegistrarResolucionRequest $request, Solicitud $solicitud, DesignacionService $designacion): RedirectResponse
    {
        $resolucion = $designacion->registrarResolucion($solicitud, $this->identidad($request), $request->validated(), $request->file('archivo'));

        return redirect()->route('tesis-proyecto-tesis.secretaria.resoluciones')
            ->with('status', "Resolución N.° {$resolucion->numero} registrada. Se notificó al asesor, a los estudiantes y al Director.");
    }
}
