<?php

namespace App\Modules\InvestigacionFormativa\Controllers\Concerns;

use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Models\Encuesta;
use App\Modules\InvestigacionFormativa\Requests\GuardarEncuestaRequest;
use App\Modules\InvestigacionFormativa\Requests\VersionEncuestaRequest;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Shared\Models\Core\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Acciones compartidas por la dirección y la secretaría para preparar y publicar
 * la encuesta de una sección.
 *
 * Cada controlador define sus rutas, su vista y si puede cerrar la encuesta.
 */
trait PreparaEncuestas
{
    abstract protected function nombreRutaEdicion(): string;

    abstract protected function nombreRutaActualizar(): string;

    abstract protected function nombreRutaPublicar(): string;

    /**
     * Ruta de cierre de la encuesta. Es null en los espacios que no cierran.
     */
    abstract protected function nombreRutaCerrar(): ?string;

    abstract protected function vistaEncuesta(): string;

    public function editarEncuesta(Request $request, Seccion $seccion): View
    {
        Gate::authorize('prepararEncuesta', $seccion);
        $this->actorDe($request);

        return view($this->vistaEncuesta(), $this->datosDeVista($seccion));
    }

    public function actualizarEncuesta(
        GuardarEncuestaRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        Gate::authorize('prepararEncuesta', $seccion);
        $actor = $this->actorDe($request);

        try {
            $this->encuestas->guardarBorrador(
                $actor,
                (int) $seccion->id_seccion,
                array_values((array) $request->validated('preguntas')),
                $request->integer('version'),
            );
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (VersionDesactualizada $exception) {
            return $this->vistaConConflicto(
                $seccion,
                $exception->getMessage(),
                409,
                array_values((array) $request->validated('preguntas')),
                $request->integer('version'),
            );
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route($this->nombreRutaEdicion(), $seccion)
                ->withInput()
                ->withErrors(['encuesta' => $exception->getMessage()]);
        }

        return redirect()
            ->route($this->nombreRutaEdicion(), $seccion)
            ->with('status', 'El borrador de la encuesta se guardó correctamente.');
    }

    public function publicarEncuesta(
        VersionEncuestaRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        Gate::authorize('prepararEncuesta', $seccion);
        $actor = $this->actorDe($request);
        $encuesta = $this->encuestaDeSeccion($seccion);

        try {
            $this->encuestas->publicar($actor, $encuesta, $request->integer('version'));
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        } catch (VersionDesactualizada $exception) {
            return $this->vistaConConflicto($seccion, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route($this->nombreRutaEdicion(), $seccion)
                ->withErrors(['encuesta' => $exception->getMessage()]);
        }

        return redirect()
            ->route($this->nombreRutaEdicion(), $seccion)
            ->with('status', 'La encuesta se publicó. Las preguntas ya no pueden cambiarse.');
    }

    private function actorDe(Request $request): ActorInvestigacion
    {
        try {
            return $this->acceso->resolver($request->user());
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }
    }

    private function encuestaDeSeccion(Seccion $seccion): Encuesta
    {
        $encuesta = Encuesta::query()
            ->where('id_seccion', (int) $seccion->id_seccion)
            ->first();

        if ($encuesta === null) {
            abort(404);
        }

        return $encuesta;
    }

    private function vistaConConflicto(
        Seccion $seccion,
        ?string $conflicto,
        int $status,
        ?array $preguntasIntentadas = null,
        ?int $versionIntentada = null,
    ): Response {
        return response()->view(
            $this->vistaEncuesta(),
            $this->datosDeVista($seccion, $conflicto, $preguntasIntentadas, $versionIntentada),
            $status,
        );
    }

    /**
     * @param  list<array<string, mixed>>|null  $preguntasIntentadas
     * @return array<string, mixed>
     */
    private function datosDeVista(
        Seccion $seccion,
        ?string $conflicto = null,
        ?array $preguntasIntentadas = null,
        ?int $versionIntentada = null,
    ): array {
        return [
            'seccion' => $seccion,
            'encuesta' => $this->encuestas->vistaGestor((int) $seccion->id_seccion),
            'conflicto' => $conflicto,
            'preguntasIntentadas' => $preguntasIntentadas,
            'versionIntentada' => $versionIntentada,
            'rutaActualizar' => $this->nombreRutaActualizar(),
            'rutaPublicar' => $this->nombreRutaPublicar(),
            'rutaCerrar' => $this->nombreRutaCerrar(),
        ];
    }
}
