<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Controllers\Concerns\PreparaEncuestas;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Requests\GuardarEncuestaRequest;
use App\Modules\InvestigacionFormativa\Requests\ResponderEncuestaRequest;
use App\Modules\InvestigacionFormativa\Requests\VersionEncuestaRequest;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\EncuestaService;
use App\Shared\Models\Core\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class EncuestaController extends Controller
{
    use PreparaEncuestas;

    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly EncuestaService $encuestas,
    ) {}

    public function editar(Request $request, Seccion $seccion): View
    {
        return $this->editarEncuesta($request, $seccion);
    }

    public function actualizar(
        GuardarEncuestaRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        return $this->actualizarEncuesta($request, $seccion);
    }

    public function publicar(
        VersionEncuestaRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        return $this->publicarEncuesta($request, $seccion);
    }

    public function cerrar(
        VersionEncuestaRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        Gate::authorize('gestionarEncuesta', $seccion);
        $actor = $this->actorDe($request);
        $encuesta = $this->encuestaDeSeccion($seccion);

        try {
            $this->encuestas->cerrar($actor, $encuesta, $request->integer('version'));
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (VersionDesactualizada $exception) {
            return $this->vistaConConflicto($seccion, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.director.encuestas.edit', $seccion)
                ->withErrors(['encuesta' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.director.encuestas.edit', $seccion)
            ->with('status', 'La encuesta se cerró y ya no admite nuevas respuestas.');
    }

    public function mostrarEstudiante(Request $request, Seccion $seccion): View
    {
        Gate::authorize('responderEncuesta', $seccion);
        $actor = $this->actorDe($request);

        return view('modules.investigacion-formativa.alumno.encuesta', [
            'seccion' => $seccion,
            'encuesta' => $this->encuestas->vistaEstudiante($actor, (int) $seccion->id_seccion),
        ]);
    }

    public function responder(
        ResponderEncuestaRequest $request,
        Seccion $seccion,
    ): RedirectResponse {
        Gate::authorize('responderEncuesta', $seccion);
        $actor = $this->actorDe($request);
        $encuesta = $this->encuestaDeSeccion($seccion);

        if ($encuesta->estado === 'borrador') {
            abort(404);
        }

        try {
            $resultado = $this->encuestas->responder(
                $actor,
                $encuesta,
                array_values((array) $request->validated('respuestas')),
                $request->validated('comentario'),
            );
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.alumno.encuestas.show', $seccion)
                ->withInput()
                ->withErrors(['encuesta' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.alumno.encuestas.show', $seccion)
            ->with(
                'status',
                $resultado->ya_respondida
                    ? 'Ya enviaste tu respuesta. Se conserva la respuesta registrada.'
                    : 'Tu respuesta quedó registrada correctamente.',
            );
    }

    protected function nombreRutaEdicion(): string
    {
        return 'investigacion-formativa.director.encuestas.edit';
    }

    protected function nombreRutaActualizar(): string
    {
        return 'investigacion-formativa.director.encuestas.update';
    }

    protected function nombreRutaPublicar(): string
    {
        return 'investigacion-formativa.director.encuestas.publicar';
    }

    protected function nombreRutaCerrar(): ?string
    {
        return 'investigacion-formativa.director.encuestas.cerrar';
    }

    protected function vistaEncuesta(): string
    {
        return 'modules.investigacion-formativa.director.encuesta';
    }
}
