<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\CatNivelLogro;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Competencia;
use App\Modules\SeguimientoEgresado\Models\EvaluacionCompetencia;
use App\Modules\SeguimientoEgresado\Models\EvaluacionOe;
use App\Modules\SeguimientoEgresado\Models\MejoraCurricular;
use App\Modules\SeguimientoEgresado\Models\ObjetivoEducacional;
use App\Modules\SeguimientoEgresado\Models\PlanAccion;
use App\Modules\SeguimientoEgresado\Models\PlanCurricular;
use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Services\Accesos\SesionAcceso;
use App\Modules\SeguimientoEgresado\Services\CatalogosDeCampania;
use App\Modules\SeguimientoEgresado\Services\Indicadores\CalculadoraIndicadores;
use App\Modules\SeguimientoEgresado\Services\Indicadores\FiltroIndicadores;
use App\Modules\SeguimientoEgresado\Support\Esquema;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Evaluación institucional. Sigue el procedimiento oficial: la Dirección evalúa si se lograron los objetivos educacionales y las
 * competencias (informes F8 y F9, de ahí sale I3), decide qué cambiar («Dice / Debe decir», F11 y F10) y cada cambio implementado
 * con su documento suma 1 a I6. La Secretaría prepara los registros y la Dirección los valida.
 */
class InstitucionalController extends Controller
{
    /** Estados de un cambio que solo puede fijar la Dirección. */
    private const ESTADOS_DE_DIRECCION = ['APROBADA', 'EN_EJECUCION', 'IMPLEMENTADA', 'DESCARTADA'];

    public function index(Request $request, CalculadoraIndicadores $calculadora, CatalogosDeCampania $catalogos): View
    {
        $objetivos = ObjetivoEducacional::with('planCurricular')->where('vigente', true)->orderBy('codigo')->get();
        $competencias = Competencia::where('vigente', true)->orderBy('tipo')->orderBy('codigo')->get();
        $niveles = CatNivelLogro::where('activo', true)->orderBy('id')->get();
        $idPlan = $objetivos->first()?->id_plan_curricular;

        $informesOe = $this->informes(EvaluacionOe::class, 'id_objetivo', $objetivos->count());
        $informesCompetencia = $this->informes(EvaluacionCompetencia::class, 'id_competencia', $competencias->count());

        $filtro = new FiltroIndicadores;
        $i3 = $calculadora->i3($filtro);
        $i6 = $calculadora->i6($filtro);

        $ultimoDeCompetencias = $informesCompetencia->where('estado', 'VALIDADA')->first();

        return view('modules.seguimiento-egresado.paginas.institucional', [
            'objetivos' => $objetivos,
            'competencias' => $competencias,
            'niveles' => $niveles,
            'informesOe' => $informesOe,
            'informesCompetencia' => $informesCompetencia,
            'cambios' => MejoraCurricular::orderByDesc('fecha_registro')->orderByDesc('id')->get(),
            'planesAccion' => PlanAccion::orderByDesc('fecha_limite')->orderByDesc('id')->get(),
            'promociones' => Cohorte::when($idPlan, fn ($q) => $q->where('id_plan_curricular', $idPlan))->orderByDesc('anio_ingreso')->get(['id', 'anio_ingreso']),
            // Un informe describe un periodo ya cursado: no se ofrecen los futuros, pero sí los de los informes que ya existen.
            'periodos' => $catalogos->periodos()->merge($informesOe->pluck('periodo'))->merge($informesCompetencia->pluck('periodo'))
                ->filter(fn ($p) => $p <= $this->periodoActual())->unique()->sortDesc()->values(),
            'resumen' => [
                'i3_logrados' => $i3->numerador, 'i3_total' => $i3->denominador, 'i3_valor' => $i3->valor,
                'competencias_conformes' => $ultimoDeCompetencias['logrados'] ?? null, 'competencias_total' => $ultimoDeCompetencias['total'] ?? $competencias->count(),
                'i6' => (int) ($i6->valor ?? 0),
                'por_validar' => $informesOe->where('estado', 'BORRADOR')->count() + $informesCompetencia->where('estado', 'BORRADOR')->count()
                    + MejoraCurricular::whereIn('estado', ['PROPUESTA', 'EVALUACION'])->count(),
            ],
            'puedeRegistrar' => $this->puede($request, 'institucional.registrar'),
            'puedeValidar' => $this->puede($request, 'institucional.validar'),
        ]);
    }

    /** Informe de logro de los objetivos educacionales (F8): un nivel por objetivo, para una promoción y un periodo. */
    public function guardarInformeLogro(Request $request): RedirectResponse
    {
        return $this->guardarInforme($request, EvaluacionOe::class, 'id_objetivo', 'objetivo_educacional', true, 'El informe de logro de los objetivos');
    }

    /** Informe del nivel de logro de las competencias (F9). */
    public function guardarInformeCompetencias(Request $request): RedirectResponse
    {
        return $this->guardarInforme($request, EvaluacionCompetencia::class, 'id_competencia', 'competencia', false, 'El informe de las competencias');
    }

    /** Cambio «Dice / Debe decir» (F10 perfil de egreso, F11 objetivos educacionales u otro). Implementado y con documento, suma a I6. */
    public function guardarCambio(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'id' => ['nullable', 'integer', 'exists:'.Esquema::validar('mejora_curricular').',id'],
            'tipo' => ['required', 'in:PERFIL,OE,OTRA'],
            'titulo' => ['required', 'string', 'max:200'],
            'dice' => ['required_unless:tipo,OTRA', 'nullable', 'string', 'max:2000'],
            'debe_decir' => ['required_unless:tipo,OTRA', 'nullable', 'string', 'max:2000'],
            'descripcion' => ['required', 'string', 'max:2000'],
            'estado' => ['required', 'in:PROPUESTA,EVALUACION,APROBADA,EN_EJECUCION,IMPLEMENTADA,DESCARTADA'],
            'documento_referencia' => ['required_if:estado,IMPLEMENTADA', 'nullable', 'string', 'max:200'],
            'fecha_implementacion' => ['required_if:estado,IMPLEMENTADA', 'nullable', 'date', 'before_or_equal:'.$this->hoy()->toDateString()],
            'origen_seguimiento' => ['nullable', 'boolean'],
        ], [
            'tipo.required' => 'Elige qué se cambia.',
            'titulo.required' => 'Escribe un título para el cambio.',
            'dice.required_unless' => 'Escribe lo que dice hoy.',
            'debe_decir.required_unless' => 'Escribe cómo debe decir.',
            'descripcion.required' => 'Explica por qué se hace el cambio.',
            'documento_referencia.required_if' => 'Un cambio implementado necesita el documento que lo respalda (resolución o acta).',
            'fecha_implementacion.required_if' => 'Indica la fecha en que se implementó.',
            'fecha_implementacion.before_or_equal' => 'La fecha de implementación no puede ser futura.',
        ]);

        $existente = ! empty($datos['id']) ? MejoraCurricular::findOrFail($datos['id']) : null;
        if (! $this->puede($request, 'institucional.validar') && (in_array($datos['estado'], self::ESTADOS_DE_DIRECCION, true) || ($existente && in_array($existente->estado, self::ESTADOS_DE_DIRECCION, true)))) {
            return back()->withInput()->withErrors(['general' => 'Solo la Dirección aprueba, ejecuta o implementa un cambio. Déjalo como «Propuesta» o «En evaluación».']);
        }

        $implementada = $datos['estado'] === 'IMPLEMENTADA';
        $atributos = [
            'tipo' => $datos['tipo'], 'titulo' => $datos['titulo'], 'descripcion' => $datos['descripcion'], 'estado' => $datos['estado'],
            'dice' => $datos['tipo'] === 'OTRA' ? ($datos['dice'] ?? null) : $datos['dice'], 'debe_decir' => $datos['tipo'] === 'OTRA' ? ($datos['debe_decir'] ?? null) : $datos['debe_decir'],
            'origen_seguimiento' => (bool) ($datos['origen_seguimiento'] ?? true),
            'documento_referencia' => ($datos['documento_referencia'] ?? null) ?: null,
            'fecha_implementacion' => $implementada ? $datos['fecha_implementacion'] : null,
            'periodo' => $existente?->periodo ?? $this->periodoActual(),
        ];
        if (($datos['tipo'] === 'OTRA') && (($atributos['dice'] === null) !== ($atributos['debe_decir'] === null))) {
            $atributos['dice'] = $atributos['debe_decir'] = null;
        }

        if ($existente) {
            $existente->update($atributos);
            $mejora = $existente;
        } else {
            $mejora = MejoraCurricular::create($atributos + [
                'codigo' => $this->siguienteCodigo(MejoraCurricular::class, 'MC'),
                'id_plan_curricular' => $this->planDeObjetivos(),
                'fecha_registro' => $this->hoy()->toDateString(),
                'id_usuario_registro' => UsuarioInterno::idActual() ?? 2,
            ]);
        }

        $mensaje = $implementada && $mejora->origen_seguimiento
            ? "El cambio {$mejora->codigo} quedó implementado y suma 1 a I6."
            : "El cambio {$mejora->codigo} quedó guardado como «".$this->etiquetaDeEstado($mejora->estado)."».";

        return redirect()->to(route('seguimiento-egresado.institucional').'#cambios')->with('success', $mensaje);
    }

    /** Plan de acción de vinculación o de inserción laboral (F5 y F7). */
    public function guardarPlanAccion(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'id' => ['nullable', 'integer', 'exists:'.Esquema::validar('plan_accion').',id'],
            'titulo' => ['required', 'string', 'max:200'],
            'tipo' => ['required', 'in:VINCULACION,INSERCION'],
            'estado' => ['required', 'in:PLANIFICADA,EN_EJECUCION,FINALIZADA,EVALUADA'],
            'actividad' => ['required', 'string', 'max:2000'],
            'responsable' => ['nullable', 'string', 'max:160'],
            'fecha_limite' => ['nullable', 'date'],
            'grado_cumplimiento' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:2000'],
        ], [
            'titulo.required' => 'Escribe el nombre del plan.',
            'tipo.required' => 'Elige si es de vinculación o de inserción laboral.',
            'actividad.required' => 'Describe qué se hará.',
        ]);

        $atributos = $datos + ['periodo' => $this->periodoActual()];
        unset($atributos['id']);

        if (! empty($datos['id'])) {
            PlanAccion::findOrFail($datos['id'])->update($atributos);
        } else {
            PlanAccion::create($atributos + ['codigo' => $this->siguienteCodigo(PlanAccion::class, 'PA'), 'id_usuario_registro' => UsuarioInterno::idActual() ?? 2]);
        }

        return redirect()->to(route('seguimiento-egresado.institucional').'#planes')->with('success', 'El plan de acción quedó guardado.');
    }

    // ---------------------------------------------------------------------------------------------------------------

    private function guardarInforme(Request $request, string $modelo, string $columna, string $tablaItems, bool $conDecisiones, string $nombre): RedirectResponse
    {
        $datos = $request->validate([
            'id_cohorte' => ['required', 'integer', 'exists:'.Esquema::validar('cohorte').',id'],
            'periodo' => ['required', 'string', 'regex:/^\d{4}-(I|II)$/'],
            'niveles' => ['required', 'array'],
            'niveles.*' => ['required', 'integer', 'exists:'.Esquema::validar('cat_nivel_logro').',id'],
            'observacion' => ['nullable', 'string', 'max:1000'],
            'acciones_derivadas' => ['nullable', 'string', 'max:1000'],
            'accion' => ['required', 'in:borrador,validar'],
        ], [
            'id_cohorte.required' => 'Elige la promoción.',
            'periodo.required' => 'Elige el periodo.',
            'periodo.regex' => 'Elige un periodo de la lista.',
            'niveles.required' => 'Elige el nivel de logro de cada elemento.',
        ]);

        $cohorte = Cohorte::findOrFail($datos['id_cohorte']);
        $ids = DB::table('seguimiento_egresado.'.$tablaItems)->where('vigente', true)->where('id_plan_curricular', $cohorte->id_plan_curricular)->orderBy('id')->pluck('id')->all();
        if ($ids === []) {
            throw ValidationException::withMessages(['id_cohorte' => 'El plan de estudios de esa promoción no tiene elementos vigentes que evaluar.']);
        }
        foreach ($ids as $id) {
            if (empty($datos['niveles'][$id])) {
                throw ValidationException::withMessages(['niveles' => 'Elige el nivel de logro de todos los elementos.']);
            }
        }

        $puedeValidar = $this->puede($request, 'institucional.validar');
        if ($datos['accion'] === 'validar' && ! $puedeValidar) {
            return back()->withInput()->withErrors(['general' => 'Solo la Dirección puede validar el informe. Guárdalo como borrador y avísale.']);
        }
        $yaValidado = $modelo::where('id_cohorte', $cohorte->id)->where('periodo', $datos['periodo'])->where('estado', 'VALIDADA')->exists();
        if ($yaValidado && ! $puedeValidar) {
            return back()->withInput()->withErrors(['general' => 'Este informe ya está validado. Solo la Dirección puede modificarlo.']);
        }

        $estado = $datos['accion'] === 'validar' ? 'VALIDADA' : 'BORRADOR';
        $usuario = UsuarioInterno::idActual() ?? 2;

        DB::transaction(function () use ($modelo, $columna, $ids, $datos, $cohorte, $estado, $usuario, $conDecisiones) {
            foreach ($ids as $id) {
                $modelo::updateOrCreate(
                    [$columna => $id, 'id_cohorte' => $cohorte->id, 'periodo' => $datos['periodo']],
                    array_filter([
                        'id_nivel_logro' => (int) $datos['niveles'][$id], 'estado' => $estado, 'observacion' => $datos['observacion'] ?? null,
                        'acciones_derivadas' => $conDecisiones ? ($datos['acciones_derivadas'] ?? null) : false,
                        'fecha_evaluacion' => $this->hoy()->toDateString(), 'id_usuario_registro' => $usuario,
                    ], fn ($v) => $v !== false),
                );
            }
        });

        return redirect()->to(route('seguimiento-egresado.institucional').($conDecisiones ? '#logro' : '#competencias'))->with('success', $estado === 'VALIDADA'
            ? "{$nombre} de la promoción {$cohorte->anio_ingreso} ({$datos['periodo']}) quedó validado y ya cuenta en los indicadores."
            : "{$nombre} de la promoción {$cohorte->anio_ingreso} ({$datos['periodo']}) quedó como borrador, pendiente de que la Dirección lo valide.");
    }

    /**
     * Un informe por promoción y periodo, con el nivel de cada objetivo o competencia.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function informes(string $modelo, string $columna, int $totalItems): Collection
    {
        return $modelo::with(['cohorte', 'nivelLogro'])->orderByDesc('fecha_evaluacion')->get()
            ->groupBy(fn ($f) => $f->id_cohorte.'|'.$f->periodo)
            ->map(function (Collection $grupo) use ($columna, $totalItems) {
                $primero = $grupo->first();

                return [
                    'cohorte_id' => (int) $primero->id_cohorte,
                    'promocion' => $primero->cohorte?->anio_ingreso,
                    'periodo' => $primero->periodo,
                    'niveles' => $grupo->mapWithKeys(fn ($f) => [(int) $f->{$columna} => ['id' => (int) $f->id_nivel_logro, 'codigo' => $f->nivelLogro?->codigo, 'nombre' => $f->nivelLogro?->nombre]])->all(),
                    'logrados' => $grupo->filter(fn ($f) => in_array($f->nivelLogro?->codigo, ['BUENO', 'MUY_BUENO'], true))->count(),
                    'total' => max($totalItems, $grupo->count()),
                    'estado' => $grupo->every(fn ($f) => $f->estado === 'VALIDADA') ? 'VALIDADA' : 'BORRADOR',
                    'observacion' => $primero->observacion,
                    'decisiones' => $primero->acciones_derivadas ?? null,
                    'fecha' => $primero->fecha_evaluacion,
                ];
            })
            ->sortByDesc(fn ($i) => $i['periodo'].'|'.str_pad((string) $i['promocion'], 4, '0', STR_PAD_LEFT))
            ->values();
    }

    /** Códigos como MC-2026-03: el siguiente número libre del año. */
    private function siguienteCodigo(string $modelo, string $prefijo): string
    {
        $anio = $this->hoy()->year;
        $n = $modelo::where('codigo', 'like', "{$prefijo}-{$anio}-%")->count() + 1;
        while ($modelo::where('codigo', sprintf('%s-%d-%02d', $prefijo, $anio, $n))->exists()) {
            $n++;
        }

        return sprintf('%s-%d-%02d', $prefijo, $anio, $n);
    }

    /** Las fechas del módulo son las de Perú, igual que el corte de los indicadores. */
    private function hoy(): CarbonImmutable
    {
        return CarbonImmutable::now(FiltroIndicadores::ZONA);
    }

    private function periodoActual(): string
    {
        $hoy = $this->hoy();

        return $hoy->year.'-'.($hoy->month <= 7 ? 'I' : 'II');
    }

    private function planDeObjetivos(): int
    {
        return (int) (ObjetivoEducacional::where('vigente', true)->value('id_plan_curricular')
            ?? PlanCurricular::where('estado', 'VIGENTE')->value('id')
            ?? PlanCurricular::orderBy('id')->value('id'));
    }

    private function etiquetaDeEstado(string $estado): string
    {
        return ['PROPUESTA' => 'Propuesta', 'EVALUACION' => 'En evaluación', 'APROBADA' => 'Aprobada', 'EN_EJECUCION' => 'En ejecución', 'IMPLEMENTADA' => 'Implementada', 'DESCARTADA' => 'Descartada'][$estado] ?? $estado;
    }

    /** Sin el módulo de accesos integrado se permite; con él, según la capacidad del perfil. */
    private function puede(Request $request, string $capacidad): bool
    {
        if (! class_exists(SesionAcceso::class)) {
            return true;
        }

        return (bool) SesionAcceso::desde($request)->actual()?->puede($capacidad);
    }
}
