<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Grupo;
use App\Modules\InvestigacionFormativa\Requests\DecisionTrabajoRequest;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Modules\InvestigacionFormativa\Services\GrupoService;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Shared\Models\Core\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Decisiones docentes de aprobación final y trabajo destacado.
 */
final class DecisionController extends Controller
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly DecisionTrabajoService $decisiones,
        private readonly GrupoService $grupos,
    ) {}

    public function aprobar(
        DecisionTrabajoRequest $request,
        Seccion $seccion,
        Grupo $grupo,
    ): RedirectResponse {
        $this->exigirGrupoDeSeccion($seccion, $grupo);
        $idProyecto = $this->idProyectoDe($grupo);
        $actor = $this->actorDe($request);

        return $this->ejecutar(
            fn () => $this->decisiones->aprobar($actor, $idProyecto, $request->integer('version')),
            $seccion,
            $grupo,
            'La aprobación final del trabajo quedó registrada.',
        );
    }

    public function seleccionar(
        DecisionTrabajoRequest $request,
        Seccion $seccion,
        Grupo $grupo,
    ): RedirectResponse {
        $this->exigirGrupoDeSeccion($seccion, $grupo);
        $idProyecto = $this->idProyectoDe($grupo);
        $actor = $this->actorDe($request);

        return $this->ejecutar(
            fn () => $this->decisiones->seleccionar($actor, $idProyecto, $request->integer('version')),
            $seccion,
            $grupo,
            'El trabajo quedó marcado como trabajo destacado de la sección.',
        );
    }

    public function retirar(
        DecisionTrabajoRequest $request,
        Seccion $seccion,
        Grupo $grupo,
    ): RedirectResponse {
        $this->exigirGrupoDeSeccion($seccion, $grupo);
        $idProyecto = $this->idProyectoDe($grupo);
        $actor = $this->actorDe($request);

        return $this->ejecutar(
            fn () => $this->decisiones->retirarSeleccion(
                $actor,
                $idProyecto,
                $request->integer('version'),
                $request->validated('motivo'),
            ),
            $seccion,
            $grupo,
            'La selección del trabajo destacado quedó retirada.',
        );
    }

    private function ejecutar(
        callable $accion,
        Seccion $seccion,
        Grupo $grupo,
        string $mensaje,
    ): RedirectResponse {
        try {
            $accion();
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (VersionDesactualizada|EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo])
                ->withErrors(['decision' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo])
            ->with('status', $mensaje);
    }

    private function idProyectoDe(Grupo $grupo): int
    {
        $detalle = $this->grupos->detalleGrupo((int) $grupo->id_grupo);

        if ($detalle === null || $detalle->proyecto === null) {
            abort(404);
        }

        return (int) $detalle->proyecto->id_proyecto;
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
