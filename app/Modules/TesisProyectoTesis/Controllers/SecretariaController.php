<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Acta;
use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Models\Solicitud;
use App\Modules\TesisProyectoTesis\Requests\FiltrarAgendaRequest;
use App\Modules\TesisProyectoTesis\Requests\ProgramarSustentacionRequest;
use App\Modules\TesisProyectoTesis\Requests\RegistrarResolucionRequest;
use App\Modules\TesisProyectoTesis\Requests\SubirActaFirmadaRequest;
use App\Modules\TesisProyectoTesis\Services\ActaService;
use App\Modules\TesisProyectoTesis\Services\CatalogoService;
use App\Modules\TesisProyectoTesis\Services\DesignacionService;
use App\Modules\TesisProyectoTesis\Services\IdentidadService;
use App\Modules\TesisProyectoTesis\Services\RubricaService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use App\Modules\TesisProyectoTesis\Services\SimilitudService;
use App\Modules\TesisProyectoTesis\Services\SustentacionService;
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

    public function sustentaciones(Request $request, SustentacionService $sustentaciones): View
    {
        $this->identidad($request);
        $pendientes = $sustentaciones->porProgramar();

        return $this->vista($request, 'secretaria.sustentaciones', [
            'pendientes' => $pendientes,
            'recientes' => $sustentaciones->recientes(),
        ], 'secretaria', 'sustentaciones', ['sustentaciones' => $pendientes->count()]);
    }

    public function programar(FiltrarAgendaRequest $request, Proyecto $proyecto, SustentacionService $sustentaciones, SeguimientoService $seguimiento, CatalogoService $catalogo, RubricaService $rubrica, SimilitudService $similitud): View
    {
        $this->identidad($request);
        $filtro = $request->validated();
        $ambientes = $catalogo->ambientes();
        $codigo = $filtro['ambiente'] ?? old('ambiente', $ambientes[0]['codigo'] ?? null);
        $fecha = \Illuminate\Support\Carbon::parse($filtro['fecha'] ?? old('fecha', now()->toDateString()));

        return $this->vista($request, 'secretaria.programar', [
            ...$seguimiento->ficha($proyecto),
            'ambientes' => $ambientes,
            'ambienteSel' => $codigo,
            'fechaSel' => $fecha,
            'agenda' => $sustentaciones->agenda($codigo ? $catalogo->idAmbientePorCodigo($codigo) : null, $fecha),
            'participantes' => $sustentaciones->participantes($proyecto),
            'intento' => $proyecto->sustentaciones()->count() + 1,
            'programable' => in_array($proyecto->estado, SustentacionService::PROGRAMABLES, true),
            'reporte' => $similitud->historial($proyecto)->firstWhere('resultado', 'Apto'),
            'totalCriterios' => $rubrica->criterios($proyecto->etapa)->count(),
        ], 'secretaria', 'sustentaciones');
    }

    public function guardarProgramacion(ProgramarSustentacionRequest $request, Proyecto $proyecto, SustentacionService $sustentaciones): RedirectResponse
    {
        $d = $request->validated();
        $s = $sustentaciones->programar($proyecto, $this->identidad($request), $d['fecha'], $d['hora'], $d['ambiente']);

        return redirect()->route('tesis-proyecto-tesis.secretaria.sustentaciones')
            ->with('status', "Sustentación de {$proyecto->codigo} programada para el {$s->fecha_hora->format('d/m/Y H:i')}. Se notificó a los participantes.");
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
