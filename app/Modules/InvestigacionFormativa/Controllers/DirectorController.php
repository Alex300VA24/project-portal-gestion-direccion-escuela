<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Requests\HabilitarSeccionRequest;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DatosAcademicosService;
use App\Modules\InvestigacionFormativa\Services\ReportePdfService;
use App\Modules\InvestigacionFormativa\Services\ReporteService;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Shared\Models\Core\Seccion;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class DirectorController extends Controller
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly DatosAcademicosService $datos,
        private readonly ConfiguracionService $configuracion,
        private readonly ReporteService $reportes,
        private readonly ReportePdfService $pdf,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->actorDe($request);

        $filtros = $request->validate([
            'escuela' => ['nullable', 'integer'],
            'periodo' => ['nullable', 'string', 'max:20'],
            'curso' => ['nullable', 'integer'],
            'linea' => ['nullable', 'integer'],
        ]);

        $idEscuela = isset($filtros['escuela']) && $filtros['escuela'] !== null && $filtros['escuela'] !== ''
            ? (int) $filtros['escuela']
            : null;
        $periodo = isset($filtros['periodo']) && trim((string) $filtros['periodo']) !== ''
            ? trim((string) $filtros['periodo'])
            : null;
        $idCurso = isset($filtros['curso']) && $filtros['curso'] !== null && $filtros['curso'] !== ''
            ? (int) $filtros['curso']
            : null;
        $idLinea = isset($filtros['linea']) && $filtros['linea'] !== null && $filtros['linea'] !== ''
            ? (int) $filtros['linea']
            : null;

        try {
            $reporte = $this->reportes->resumenDirector($actor, $idEscuela, $periodo, $idCurso, $idLinea);
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }

        return view('modules.investigacion-formativa.director.index', [
            'escuelas' => $reporte->escuelas,
            'periodos' => $reporte->periodos,
            'cursos' => $reporte->cursos,
            'lineas' => $reporte->lineas,
            'secciones' => $reporte->secciones,
            'lineas_resumen' => $reporte->lineas_resumen,
            'umbrales' => $reporte->umbrales,
            'encuesta' => $reporte->encuesta,
            'decisiones' => $reporte->decisiones,
            'indicadores' => $reporte->indicadores,
            'totales' => $reporte->totales,
            'filtros' => [
                'escuela' => $idEscuela,
                'periodo' => $periodo,
                'curso' => $idCurso,
                'linea' => $idLinea,
            ],
        ]);
    }

    public function cursos(Request $request): Response
    {
        $actor = $this->actorDe($request);

        $filtros = $request->validate([
            'escuela' => ['nullable', 'integer'],
            'buscar' => ['nullable', 'string', 'max:100'],
            'periodo' => ['nullable', 'string', 'max:20'],
        ]);

        return $this->vistaCursos($actor->user->id, $filtros);
    }

    public function habilitacion(HabilitarSeccionRequest $request, Seccion $seccion): RedirectResponse|Response
    {
        $actor = $this->actorDe($request);

        try {
            $this->configuracion->cambiarHabilitacion(
                $actor,
                (int) $seccion->id_seccion,
                $request->boolean('habilitada'),
                $request->integer('version'),
            );
        } catch (VersionDesactualizada $exception) {
            return $this->vistaCursos($actor->user->id, [
                'escuela' => $request->input('escuela'),
                'buscar' => $request->input('buscar'),
                'periodo' => $request->input('periodo'),
            ], $exception->getMessage(), 409);
        }

        return redirect()
            ->route('investigacion-formativa.director.cursos.index', $this->filtrosDeConsulta($request))
            ->with('status', $request->boolean('habilitada')
                ? 'La sección quedó habilitada para Investigación Formativa.'
                : 'La sección quedó deshabilitada para Investigación Formativa.');
    }

    public function resultados(Request $request, Seccion $seccion): View
    {
        $actor = $this->actorDe($request);
        Gate::authorize('verResultados', $seccion);

        $pagina = max(1, $request->integer('comentarios', 1));
        $porPagina = min(100, max(1, $request->integer('por_pagina', 10)));
        $idLinea = $request->filled('linea') ? (int) $request->input('linea') : null;

        try {
            $reporte = $this->reportes->porSeccion(
                $actor,
                (int) $seccion->id_seccion,
                $pagina,
                $porPagina,
                $idLinea,
            );
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }

        $reporte->comentarios->appends($request->only(['por_pagina', 'escuela', 'periodo', 'curso', 'linea']));
        $seccion->load(['periodo', 'cursoPlan.curso']);

        return view('modules.investigacion-formativa.director.resultados', [
            'seccion' => $seccion,
            'reporte' => $reporte,
            'pagina' => $pagina,
            'porPagina' => $porPagina,
        ]);
    }

    public function exportarResultados(Request $request, Seccion $seccion): Response
    {
        $actor = $this->actorDe($request);
        Gate::authorize('verResultados', $seccion);

        $idLinea = $request->filled('linea') ? (int) $request->input('linea') : null;

        try {
            $reporte = $this->reportes->porSeccion(
                $actor,
                (int) $seccion->id_seccion,
                1,
                1,
                $idLinea,
                false,
            );
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }

        $seccion->load(['periodo', 'cursoPlan.curso']);

        $generadoEn = CarbonImmutable::now('America/Lima');
        $contenido = $this->pdf->generar(
            $reporte,
            $seccion,
            $this->datos->nombreEscuelaDeSeccion((int) $seccion->id_seccion),
            $idLinea === null ? null : $this->datos->nombreDeLinea($idLinea),
            $generadoEn->format('d/m/Y H:i'),
        );

        $codigo = Str::slug((string) $seccion->codigo);
        $nombreArchivo = 'investigacion-formativa-'
            .($codigo === '' ? 'seccion-'.$seccion->id_seccion : $codigo)
            .'-'.$generadoEn->format('Ymd-His').'.pdf';

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$nombreArchivo.'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function actorDe(Request $request): ActorInvestigacion
    {
        try {
            return $this->acceso->resolver($request->user());
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }
    }

    /**
     * @param  array{escuela?: int|string|null, buscar?: string|null, periodo?: string|null}  $filtros
     */
    private function vistaCursos(
        int $userId,
        array $filtros,
        ?string $conflicto = null,
        int $status = 200,
    ): Response {
        $escuelas = $this->datos->escuelasDeDirector($userId);
        $idEscuela = isset($filtros['escuela']) && $filtros['escuela'] !== null && $filtros['escuela'] !== ''
            ? (int) $filtros['escuela']
            : null;

        if ($idEscuela !== null && ! in_array($idEscuela, array_column($escuelas, 'id_escuela'), true)) {
            abort(403, 'La escuela solicitada no pertenece a su alcance de dirección.');
        }

        $busqueda = isset($filtros['buscar']) && trim((string) $filtros['buscar']) !== ''
            ? trim((string) $filtros['buscar'])
            : null;
        $periodo = isset($filtros['periodo']) && trim((string) $filtros['periodo']) !== ''
            ? trim((string) $filtros['periodo'])
            : null;

        $secciones = $this->datos->listarSeccionesDeDirector($userId, $idEscuela, $busqueda, $periodo);

        $secciones->getCollection()->transform(function (object $seccion): object {
            $estado = $this->configuracion->estado($seccion->id_seccion);

            $seccion->configurada = $estado->configurada;
            $seccion->faltantes = $estado->faltantes;
            $seccion->version_configuracion = $estado->version;

            return $seccion;
        });

        $secciones->appends(array_filter([
            'escuela' => $idEscuela,
            'buscar' => $busqueda,
            'periodo' => $periodo,
        ], static fn (mixed $valor): bool => $valor !== null));

        return response()->view('modules.investigacion-formativa.director.cursos', [
            'escuelas' => $escuelas,
            'periodos' => $this->datos->periodosDeDirector($userId),
            'secciones' => $secciones,
            'filtros' => [
                'escuela' => $idEscuela,
                'buscar' => $busqueda,
                'periodo' => $periodo,
            ],
            'conflicto' => $conflicto,
        ], $status);
    }

    /** @return array<string, int|string> */
    private function filtrosDeConsulta(Request $request): array
    {
        return array_filter([
            'escuela' => $request->input('escuela'),
            'buscar' => $request->input('buscar'),
            'periodo' => $request->input('periodo'),
        ], static fn (mixed $valor): bool => $valor !== null && $valor !== '');
    }
}
