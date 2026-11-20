<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Controllers\Concerns\PreparaEncuestas;
use App\Modules\InvestigacionFormativa\Requests\GuardarEncuestaRequest;
use App\Modules\InvestigacionFormativa\Requests\VersionEncuestaRequest;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\DatosAcademicosService;
use App\Modules\InvestigacionFormativa\Services\EncuestaService;
use App\Shared\Models\Core\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Espacio de trabajo de la secretaría de escuela.
 *
 * La secretaría prepara y publica la encuesta de las secciones habilitadas de
 * las escuelas asignadas de forma explícita. El cierre y los resultados
 * confidentiales siguen a cargo de la dirección.
 */
final class SecretariaController extends Controller
{
    use PreparaEncuestas;

    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly DatosAcademicosService $datos,
        private readonly EncuestaService $encuestas,
    ) {}

    public function index(Request $request): View
    {
        $actor = $this->actorDe($request);

        return view('modules.investigacion-formativa.secretaria.index', [
            'escuelas' => $this->datos->escuelasDeSecretaria($actor->user->id),
            'secciones' => $this->datos->seccionesHabilitadasDeSecretaria($actor->user->id),
        ]);
    }

    public function encuesta(Request $request, Seccion $seccion): View
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

    protected function nombreRutaEdicion(): string
    {
        return 'investigacion-formativa.secretaria.encuestas.edit';
    }

    protected function nombreRutaActualizar(): string
    {
        return 'investigacion-formativa.secretaria.encuestas.update';
    }

    protected function nombreRutaPublicar(): string
    {
        return 'investigacion-formativa.secretaria.encuestas.publicar';
    }

    /**
     * La secretaría no cierra la encuesta. El cierre queda a cargo de la dirección.
     */
    protected function nombreRutaCerrar(): ?string
    {
        return null;
    }

    protected function vistaEncuesta(): string
    {
        return 'modules.investigacion-formativa.secretaria.encuesta';
    }
}
