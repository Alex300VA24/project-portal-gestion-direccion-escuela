<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Controllers\Concerns\PreparaDecisionesTrabajo;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\RecursoBloqueado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Grupo;
use App\Modules\InvestigacionFormativa\Models\Proyecto;
use App\Modules\InvestigacionFormativa\Requests\ActualizarTituloRequest;
use App\Modules\InvestigacionFormativa\Requests\CrearGrupoRequest;
use App\Modules\InvestigacionFormativa\Requests\RevisarTituloRequest;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DatosAcademicosService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Modules\InvestigacionFormativa\Services\EvaluacionService;
use App\Modules\InvestigacionFormativa\Services\GrupoService;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Shared\Models\Core\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class GrupoController extends Controller
{
    use PreparaDecisionesTrabajo;

    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly ConfiguracionService $configuracion,
        private readonly DatosAcademicosService $datos,
        private readonly DecisionTrabajoService $decisiones,
        private readonly EvaluacionService $evaluaciones,
        private readonly GrupoService $grupos,
    ) {}

    public function cursos(Request $request): View
    {
        $actor = $this->actorDe($request);
        $secciones = $this->datos->seccionesDeEstudiante($actor->idPersona);

        foreach ($secciones as $seccion) {
            $estado = $this->configuracion->estado($seccion->id_seccion);

            $seccion->habilitada = $estado->habilitada;
            $seccion->configurada = $estado->configurada;
            $seccion->faltantes = $estado->faltantes;
        }

        return view('modules.investigacion-formativa.alumno.cursos', [
            'secciones' => $secciones,
            'retiradas' => $this->datos->seccionesRetiradasDeEstudiante($actor->idPersona),
        ]);
    }

    public function crear(Request $request, Seccion $seccion): View|RedirectResponse
    {
        Gate::authorize('crearGrupo', $seccion);

        $actor = $this->actorDe($request);
        $idSeccion = (int) $seccion->id_seccion;
        $existente = $this->grupos->grupoDeEstudianteEnSeccion($actor->idPersona, $idSeccion);

        if ($existente !== null) {
            return redirect()
                ->route('investigacion-formativa.alumno.grupos.show', $seccion)
                ->with('status', 'Ya tienes un grupo formado en esta sección.');
        }

        $estado = $this->configuracion->estado($idSeccion);

        if (! $estado->habilitada || ! $estado->configurada) {
            return redirect()
                ->route('investigacion-formativa.alumno.cursos.index')
                ->withErrors([
                    'grupo' => 'La sección todavía no está habilitada y configurada para formar grupos.',
                ]);
        }

        $datosSeccion = $this->datos->seccionConEscuela($idSeccion);
        $idEscuela = $datosSeccion?->id_escuela;
        $idEstudiante = $idEscuela === null
            ? null
            : $this->acceso->idEstudianteEnEscuela($actor, $idEscuela);

        if ($idEscuela === null || $idEstudiante === null) {
            abort(403, 'No tiene una identidad de estudiante activa en la escuela de la sección.');
        }

        return view('modules.investigacion-formativa.alumno.grupo-crear', [
            'seccion' => $seccion,
            'roster' => $this->datos->rosterActivoDeSeccion($idSeccion),
            'lineas' => $this->datos->lineasActivasDeEscuela($idEscuela),
            'tiposProducto' => $this->datos->tiposProducto(),
            'idEstudiante' => $idEstudiante,
        ]);
    }

    public function store(CrearGrupoRequest $request, Seccion $seccion): RedirectResponse
    {
        $actor = $this->actorDe($request);

        try {
            $this->grupos->crear($actor, (int) $seccion->id_seccion, $request->validated());
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.alumno.grupos.create', $seccion)
                ->withInput()
                ->withErrors(['grupo' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.alumno.grupos.show', $seccion)
            ->with('status', 'El grupo se creó correctamente. El título quedó registrado para revisión.');
    }

    public function mostrar(Request $request, Seccion $seccion): RedirectResponse|Response
    {
        $actor = $this->actorDe($request);
        $idSeccion = (int) $seccion->id_seccion;
        $resumen = $this->grupos->grupoDeEstudianteEnSeccion($actor->idPersona, $idSeccion);

        if ($resumen === null) {
            if ($this->acceso->puedeCrearGrupo($actor->user, $idSeccion)) {
                return redirect()
                    ->route('investigacion-formativa.alumno.grupos.create', $seccion)
                    ->with('status', 'Todavía no tienes un grupo en esta sección.');
            }

            abort(404);
        }

        return $this->vistaEstudiante($seccion, $actor);
    }

    public function actualizarTitulo(
        ActualizarTituloRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        $actor = $this->actorDe($request);
        $proyecto = $this->proyectoDelEstudiante($seccion, $actor);

        if ($proyecto === null) {
            abort(404);
        }

        try {
            $this->grupos->actualizarTitulo(
                $actor,
                (int) $proyecto->id_proyecto,
                (string) $request->validated('titulo'),
                $request->integer('version'),
            );
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (VersionDesactualizada|RecursoBloqueado $exception) {
            return $this->vistaEstudiante($seccion, $actor, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.alumno.grupos.show', $seccion)
                ->withInput()
                ->withErrors(['titulo' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.alumno.grupos.show', $seccion)
            ->with('status', 'El título se actualizó y volvió al estado Registrado para revisión.');
    }

    public function docenteIndex(Request $request, Seccion $seccion): View
    {
        Gate::authorize('verComoProfesor', $seccion);

        $filtros = $request->validate([
            'buscar' => ['nullable', 'string', 'max:100'],
        ]);

        $busqueda = isset($filtros['buscar']) && trim((string) $filtros['buscar']) !== ''
            ? trim((string) $filtros['buscar'])
            : null;

        return view('modules.investigacion-formativa.docente.grupos', [
            'seccion' => $seccion,
            'grupos' => $this->grupos->listarGruposDeSeccion((int) $seccion->id_seccion, $busqueda),
            'busqueda' => $busqueda,
            'decisiones' => $this->decisiones->estadoDeSeccion((int) $seccion->id_seccion)->por_grupo,
        ]);
    }

    public function docenteGrupo(Seccion $seccion, Grupo $grupo): Response
    {
        $this->exigirGrupoDeSeccion($seccion, $grupo);
        Gate::authorize('revisarTitulo', $grupo);

        return $this->vistaDocente($seccion, $grupo);
    }

    public function revisarTitulo(
        RevisarTituloRequest $request,
        Seccion $seccion,
        Grupo $grupo,
    ): RedirectResponse|Response {
        $this->exigirGrupoDeSeccion($seccion, $grupo);
        Gate::authorize('revisarTitulo', $grupo);

        $detalle = $this->grupos->detalleGrupo((int) $grupo->id_grupo);

        if ($detalle === null || $detalle->proyecto === null) {
            abort(404);
        }

        $actor = $this->actorDe($request);

        try {
            $this->grupos->revisarTitulo(
                $actor,
                $detalle->proyecto->id_proyecto,
                (string) $request->validated('estado'),
                $request->validated('motivo'),
                $request->integer('version'),
            );
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (VersionDesactualizada|RecursoBloqueado $exception) {
            return $this->vistaDocente($seccion, $grupo, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo])
                ->withInput()
                ->withErrors(['titulo' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo])
            ->with('status', 'La revisión del título quedó registrada.');
    }

    private function vistaEstudiante(
        Seccion $seccion,
        ActorInvestigacion $actor,
        ?string $conflicto = null,
        int $status = 200,
    ): Response {
        $idSeccion = (int) $seccion->id_seccion;
        $resumen = $this->grupos->grupoDeEstudianteEnSeccion($actor->idPersona, $idSeccion);

        if ($resumen === null) {
            abort(404);
        }

        $grupo = Grupo::query()->findOrFail($resumen->id_grupo);
        Gate::authorize('view', $grupo);

        $detalle = $this->grupos->detalleGrupo((int) $grupo->id_grupo);
        $datosSeccion = $this->datos->seccionConEscuela($idSeccion);
        $idEstudiante = $datosSeccion?->id_escuela === null
            ? null
            : $this->acceso->idEstudianteEnEscuela($actor, $datosSeccion->id_escuela);
        $participa = $idEstudiante !== null
            && $this->acceso->participanteActivo($idSeccion, $idEstudiante);

        $motivoBloqueo = null;

        if ($resumen->es_coordinador) {
            $motivoBloqueo = $participa
                ? $this->grupos->motivoBloqueoTitulo(
                    (int) ($detalle?->proyecto->id_proyecto ?? 0),
                    $idSeccion,
                )
                : 'Tu participación está retirada; el grupo queda en solo lectura.';
        }

        return response()->view('modules.investigacion-formativa.alumno.grupo', [
            'seccion' => $seccion,
            'detalle' => $detalle,
            'esCoordinador' => $resumen->es_coordinador,
            'participa' => $participa,
            'motivoBloqueo' => $motivoBloqueo,
            'conflicto' => $conflicto,
            'decision' => $this->decisiones->estadoDeSeccion($idSeccion)->por_grupo[(int) $grupo->id_grupo] ?? null,
        ], $status);
    }

    private function vistaDocente(
        Seccion $seccion,
        Grupo $grupo,
        ?string $conflicto = null,
        int $status = 200,
    ): Response {
        $detalle = $this->grupos->detalleGrupo((int) $grupo->id_grupo);

        return response()->view('modules.investigacion-formativa.docente.grupo', [
            'seccion' => $seccion,
            'grupo' => $grupo,
            'detalle' => $detalle,
            'evaluaciones' => $this->evaluaciones->detalleEntregasDeGrupo((int) $grupo->id_grupo),
            'conflicto' => $conflicto,
            ...$this->datosDecisiones($this->decisiones, $this->configuracion, $seccion, $grupo, $detalle),
        ], $status);
    }

    private function proyectoDelEstudiante(Seccion $seccion, ActorInvestigacion $actor): ?Proyecto
    {
        $resumen = $this->grupos->grupoDeEstudianteEnSeccion($actor->idPersona, (int) $seccion->id_seccion);

        if ($resumen === null) {
            return null;
        }

        return Proyecto::query()
            ->where('id_grupo', $resumen->id_grupo)
            ->first();
    }

    private function exigirGrupoDeSeccion(Seccion $seccion, Grupo $grupo): void
    {
        if ((int) $grupo->id_seccion !== (int) $seccion->id_seccion) {
            abort(404);
        }
    }

    private function actorDe(Request $request): ActorInvestigacion
    {
        try {
            return $this->acceso->resolver($request->user());
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }
    }
}
