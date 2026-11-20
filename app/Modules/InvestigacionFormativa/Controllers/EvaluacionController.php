<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Controllers\Concerns\PreparaDecisionesTrabajo;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Entrega;
use App\Modules\InvestigacionFormativa\Models\Grupo;
use App\Modules\InvestigacionFormativa\Requests\GuardarEvaluacionRequest;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Modules\InvestigacionFormativa\Services\EvaluacionService;
use App\Modules\InvestigacionFormativa\Services\GrupoService;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Shared\Models\Core\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class EvaluacionController extends Controller
{
    use PreparaDecisionesTrabajo;

    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly ConfiguracionService $configuracion,
        private readonly DecisionTrabajoService $decisiones,
        private readonly EvaluacionService $evaluaciones,
        private readonly GrupoService $grupos,
    ) {}

    public function update(
        GuardarEvaluacionRequest $request,
        Seccion $seccion,
        Grupo $grupo,
        Entrega $entrega,
    ): RedirectResponse|Response {
        $this->exigirGrupoDeSeccion($seccion, $grupo);
        $this->exigirEntregaDeGrupo($seccion, $grupo, $entrega);
        Gate::authorize('evaluar', $grupo);

        $actor = $this->actorDe($request);

        try {
            $this->evaluaciones->guardar(
                $actor,
                $entrega,
                array_values((array) $request->validated('criterios')),
                $request->validated('comentario'),
                $request->boolean('sin_observaciones'),
                array_values((array) $request->validated('observaciones', [])),
                $request->integer('version'),
            );
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (VersionDesactualizada $exception) {
            return $this->vistaDocente($seccion, $grupo, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo])
                ->withInput()
                ->withErrors(['evaluacion' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.docente.grupos.show', [$seccion, $grupo])
            ->with('status', 'La evaluación quedó registrada correctamente.');
    }

    private function actorDe(GuardarEvaluacionRequest $request): ActorInvestigacion
    {
        try {
            return $this->acceso->resolver($request->user());
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }
    }

    private function vistaDocente(
        Seccion $seccion,
        Grupo $grupo,
        ?string $conflicto,
        int $status,
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

    private function exigirGrupoDeSeccion(Seccion $seccion, Grupo $grupo): void
    {
        if ((int) $grupo->id_seccion !== (int) $seccion->id_seccion) {
            abort(404);
        }
    }

    private function exigirEntregaDeGrupo(Seccion $seccion, Grupo $grupo, Entrega $entrega): void
    {
        $existe = DB::table('investigacion_formativa.entrega as e')
            ->join('investigacion_formativa.proyecto as p', 'p.id_proyecto', '=', 'e.id_proyecto')
            ->join('investigacion_formativa.entregable as en', 'en.id_entregable', '=', 'e.id_entregable')
            ->where('e.id_entrega', (int) $entrega->id_entrega)
            ->where('p.id_grupo', (int) $grupo->id_grupo)
            ->where('en.id_seccion', (int) $seccion->id_seccion)
            ->exists();

        if (! $existe) {
            abort(404);
        }
    }
}
