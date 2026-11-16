<?php

namespace App\Modules\TesisProyectoTesis\Controllers;

use App\Modules\TesisProyectoTesis\Models\Proyecto;
use App\Modules\TesisProyectoTesis\Requests\AsignarJuradoRequest;
use App\Modules\TesisProyectoTesis\Requests\ReemplazarJuradoRequest;
use App\Modules\TesisProyectoTesis\Requests\SolicitarAsesorRequest;
use App\Modules\TesisProyectoTesis\Services\DesignacionService;
use App\Modules\TesisProyectoTesis\Services\DirectorService;
use App\Modules\TesisProyectoTesis\Services\JuradoService;
use App\Modules\TesisProyectoTesis\Services\SeguimientoService;
use App\Modules\TesisProyectoTesis\Services\SimilitudService;
use App\Modules\TesisProyectoTesis\Support\Estados;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class DirectorController extends BaseController
{
    public function __construct(private readonly DirectorService $director) {}

    public function panel(Request $request): View
    {
        $this->identidad($request);
        $datos = $this->director->panel();

        return $this->vista($request, 'director.panel', $datos, 'director', 'panel', ['panel' => $datos['cifras']['accion']]);
    }

    public function proyectos(Request $request): View
    {
        $this->identidad($request);
        $filtros = $request->validate([
            'estado' => ['nullable', Rule::in(array_keys(Estados::ETIQUETAS))],
            'buscar' => ['nullable', 'string', 'max:80'],
        ]);

        return $this->vista($request, 'director.proyectos', [
            'filas' => $this->director->proyectos($filtros['estado'] ?? null, $filtros['buscar'] ?? null),
            'filtros' => $filtros,
        ], 'director', 'proyectos');
    }

    public function solicitarAsesor(Request $request, Proyecto $proyecto, DesignacionService $designacion, SeguimientoService $seguimiento): View
    {
        $this->identidad($request);

        return $this->vista($request, 'director.solicitar-asesor', [
            ...$seguimiento->ficha($proyecto),
            'pendiente' => $designacion->solicitudPendiente($proyecto),
            'sugerencias' => $designacion->sugerencias($proyecto),
            'puedeSolicitar' => $proyecto->estado === Estados::REGISTRADO && $designacion->solicitudPendiente($proyecto) === null,
        ], 'director', 'panel');
    }

    public function jurado(Request $request, Proyecto $proyecto, JuradoService $jurado, SeguimientoService $seguimiento, SimilitudService $similitud): View
    {
        $this->identidad($request);
        $asesor = $proyecto->asesorActivo()->value('id_docente');

        return $this->vista($request, 'director.jurado', [
            ...$seguimiento->ficha($proyecto),
            'candidatos' => $jurado->candidatos($proyecto),
            'miembros' => $jurado->avanceMiembros($proyecto),
            'conformidad' => $asesor ? $similitud->requisitos($proyecto, (int) $asesor) : null,
            'reporte' => $similitud->historial($proyecto)->firstWhere('resultado', 'Apto'),
            'puedeAsignar' => $proyecto->estado === Estados::VERIFICADO_ASESOR && ! $proyecto->juradosActivos()->exists(),
            'puedeReemplazar' => $proyecto->juradosActivos()->exists() && ! in_array($proyecto->estado, [Estados::PROGRAMADO, Estados::SUSTENTADO, Estados::TESIS_APROBADA], true),
            'historialJurado' => $proyecto->jurados()->where('estado', 'Reemplazado')->get(['cargo', 'id_docente', 'fecha_fin', 'motivo_cambio']),
        ], 'director', 'panel');
    }

    public function asignarJurado(AsignarJuradoRequest $request, Proyecto $proyecto, JuradoService $jurado): RedirectResponse
    {
        $jurado->asignar($proyecto, $this->identidad($request), $request->validated());

        return redirect()->route('tesis-proyecto-tesis.director.panel')->with('status', "Jurado asignado a {$proyecto->codigo}. Se notificó a los tres miembros.");
    }

    public function reemplazarJurado(ReemplazarJuradoRequest $request, Proyecto $proyecto, JuradoService $jurado): RedirectResponse
    {
        $d = $request->validated();
        $jurado->reemplazar($proyecto, $this->identidad($request), $d['cargo'], $d['docente'], $d['motivo']);

        return redirect()->route('tesis-proyecto-tesis.director.jurado', $proyecto)->with('status', "Se reemplazó al {$d['cargo']} del jurado.");
    }

    public function enviarSolicitudAsesor(SolicitarAsesorRequest $request, Proyecto $proyecto, DesignacionService $designacion): RedirectResponse
    {
        $designacion->solicitarAsesor($proyecto, $this->identidad($request), $request->validated());

        return redirect()->route('tesis-proyecto-tesis.director.panel')
            ->with('status', "Solicitud de asesor enviada al Decanato para {$proyecto->codigo}. La Secretaría registrará la resolución.");
    }
}
