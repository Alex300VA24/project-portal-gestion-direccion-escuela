<?php

namespace App\Modules\InvestigacionFormativa\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Exceptions\EstadoInvalido;
use App\Modules\InvestigacionFormativa\Exceptions\RecursoBloqueado;
use App\Modules\InvestigacionFormativa\Exceptions\VersionDesactualizada;
use App\Modules\InvestigacionFormativa\Requests\GuardarCompetenciasRequest;
use App\Modules\InvestigacionFormativa\Requests\GuardarCronogramaRequest;
use App\Modules\InvestigacionFormativa\Requests\GuardarRubricaRequest;
use App\Modules\InvestigacionFormativa\Services\AccesoInvestigacionService;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DatosAcademicosService;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use App\Shared\Models\Core\Seccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class ConfiguracionController extends Controller
{
    public function __construct(
        private readonly AccesoInvestigacionService $acceso,
        private readonly ConfiguracionService $configuracion,
        private readonly DatosAcademicosService $datos,
    ) {}

    public function cursos(Request $request): View
    {
        $actor = $this->actorDe($request);
        $secciones = $actor->idDocente === null
            ? []
            : $this->datos->seccionesDeDocente($actor->idDocente);

        foreach ($secciones as $seccion) {
            $estado = $this->configuracion->estado($seccion->id_seccion);

            $seccion->configurada = $estado->configurada;
            $seccion->faltantes = $estado->faltantes;
            $seccion->version_configuracion = $estado->version;
        }

        return view('modules.investigacion-formativa.docente.cursos', [
            'secciones' => $secciones,
        ]);
    }

    public function competencias(Seccion $seccion): Response
    {
        Gate::authorize('configurar', $seccion);

        return $this->vistaCompetencias($seccion);
    }

    public function actualizarCompetencias(
        GuardarCompetenciasRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        $actor = $this->actorDe($request);

        try {
            $this->configuracion->guardarCompetencias(
                $actor,
                (int) $seccion->id_seccion,
                array_map('intval', (array) $request->validated('competencia_ids')),
                $request->integer('version'),
            );
        } catch (VersionDesactualizada|RecursoBloqueado $exception) {
            $request->flash();

            return $this->vistaCompetencias($seccion, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.docente.competencias.edit', $seccion)
                ->withInput()
                ->withErrors(['competencias' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.docente.competencias.edit', $seccion)
            ->with('status', 'Competencias guardadas. La versión de configuración se actualizó.');
    }

    public function rubrica(Seccion $seccion): Response
    {
        Gate::authorize('configurar', $seccion);

        return $this->vistaRubrica($seccion);
    }

    public function actualizarRubrica(
        GuardarRubricaRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        $actor = $this->actorDe($request);

        try {
            $datos = $request->validated();
            $datos['criterios'] = array_values((array) $datos['criterios']);

            $this->configuracion->guardarRubrica(
                $actor,
                (int) $seccion->id_seccion,
                $datos,
                $request->integer('version'),
            );
        } catch (VersionDesactualizada|RecursoBloqueado $exception) {
            $request->flash();

            return $this->vistaRubrica($seccion, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.docente.rubrica.edit', $seccion)
                ->withInput()
                ->withErrors(['rubrica' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.docente.rubrica.edit', $seccion)
            ->with('status', 'Rúbrica guardada. La versión de configuración se actualizó.');
    }

    public function cronograma(Seccion $seccion): Response
    {
        Gate::authorize('configurar', $seccion);

        return $this->vistaCronograma($seccion);
    }

    public function actualizarCronograma(
        GuardarCronogramaRequest $request,
        Seccion $seccion,
    ): RedirectResponse|Response {
        $actor = $this->actorDe($request);

        try {
            $this->configuracion->guardarCronograma(
                $actor,
                (int) $seccion->id_seccion,
                array_values((array) $request->validated('fechas')),
                $request->integer('version'),
            );
        } catch (VersionDesactualizada|RecursoBloqueado $exception) {
            $request->flash();

            return $this->vistaCronograma($seccion, $exception->getMessage(), 409);
        } catch (EstadoInvalido $exception) {
            return redirect()
                ->route('investigacion-formativa.docente.cronograma.edit', $seccion)
                ->withInput()
                ->withErrors(['cronograma' => $exception->getMessage()]);
        }

        return redirect()
            ->route('investigacion-formativa.docente.cronograma.edit', $seccion)
            ->with('status', 'Cronograma guardado. La versión de configuración se actualizó.');
    }

    private function actorDe(Request $request): ActorInvestigacion
    {
        try {
            return $this->acceso->resolver($request->user());
        } catch (AccesoDenegado $exception) {
            abort(403, $exception->getMessage());
        }
    }

    private function vistaCompetencias(
        Seccion $seccion,
        ?string $conflicto = null,
        int $status = 200,
    ): Response {
        $idSeccion = (int) $seccion->id_seccion;

        return response()->view('modules.investigacion-formativa.docente.competencias', [
            'seccion' => $seccion,
            'estado' => $this->configuracion->estado($idSeccion),
            'competencias' => $this->configuracion->competencias($idSeccion),
            'conflicto' => $conflicto,
        ], $status);
    }

    private function vistaRubrica(
        Seccion $seccion,
        ?string $conflicto = null,
        int $status = 200,
    ): Response {
        $idSeccion = (int) $seccion->id_seccion;
        $rubrica = $this->configuracion->rubrica($idSeccion);

        return response()->view('modules.investigacion-formativa.docente.rubrica', [
            'seccion' => $seccion,
            'estado' => $this->configuracion->estado($idSeccion),
            'rubrica' => $rubrica,
            'criterios' => $this->normalizarCriterios(
                old('criterios', $rubrica?->criterios ?? self::criteriosVacios()),
            ),
            'congelada' => $this->configuracion->rubricaCongelada($idSeccion),
            'conflicto' => $conflicto,
        ], $status);
    }

    private function vistaCronograma(
        Seccion $seccion,
        ?string $conflicto = null,
        int $status = 200,
    ): Response {
        $idSeccion = (int) $seccion->id_seccion;
        $cronograma = $this->configuracion->cronograma($idSeccion);

        $fechas = old('fechas', array_map(
            static fn (object $unidad): string => (string) ($unidad->fecha_limite_local ?? ''),
            $cronograma,
        ));
        $fechas = array_values(is_array($fechas) ? $fechas : []);

        for ($indice = 0; $indice < 3; $indice++) {
            $fechas[$indice] = (string) ($fechas[$indice] ?? '');
        }

        return response()->view('modules.investigacion-formativa.docente.cronograma', [
            'seccion' => $seccion,
            'estado' => $this->configuracion->estado($idSeccion),
            'fechas' => $fechas,
            'conflicto' => $conflicto,
        ], $status);
    }

    /**
     * @return list<array{descripcion: string, niveles: list<array{nivel: int, descripcion: string, puntaje: int|float|string}>}>
     */
    private function normalizarCriterios(mixed $criterios): array
    {
        $normalizados = [];

        foreach (is_array($criterios) ? $criterios : [] as $criterio) {
            $criterio = is_object($criterio) ? (array) $criterio : $criterio;

            if (! is_array($criterio)) {
                continue;
            }

            $porNivel = [];

            foreach (is_array($criterio['niveles'] ?? null) ? $criterio['niveles'] : [] as $nivel) {
                $nivel = is_object($nivel) ? (array) $nivel : $nivel;

                if (is_array($nivel)) {
                    $porNivel[(int) ($nivel['nivel'] ?? 0)] = $nivel;
                }
            }

            $niveles = [];

            foreach ([1, 2, 3, 4] as $numero) {
                $niveles[] = [
                    'nivel' => $numero,
                    'descripcion' => (string) ($porNivel[$numero]['descripcion'] ?? ''),
                    'puntaje' => $porNivel[$numero]['puntaje'] ?? '',
                ];
            }

            $normalizados[] = [
                'descripcion' => (string) ($criterio['descripcion'] ?? ''),
                'niveles' => $niveles,
            ];
        }

        return $normalizados === [] ? self::criteriosVacios() : $normalizados;
    }

    /**
     * @return list<array{descripcion: string, niveles: list<array{nivel: int, descripcion: string, puntaje: string}>}>
     */
    private static function criteriosVacios(): array
    {
        return [[
            'descripcion' => '',
            'niveles' => array_map(
                static fn (int $nivel): array => [
                    'nivel' => $nivel,
                    'descripcion' => '',
                    'puntaje' => '',
                ],
                [1, 2, 3, 4],
            ),
        ]];
    }
}
