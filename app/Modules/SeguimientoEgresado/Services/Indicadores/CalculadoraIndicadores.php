<?php

namespace App\Modules\SeguimientoEgresado\Services\Indicadores;

use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Calcula I1–I6 con las fórmulas de Indicadores_Oficiales.xlsx.
 * En PostgreSQL y con corte de hoy consume IndicadoresService y las vistas vw_* (docs/base-datos-postgresql.md).
 * Las tablas se consultan solo para lo que las vistas no resuelven (fecha de corte pasada, cohorte con horizonte)
 * y como respaldo en SQLite; las pruebas de PostgreSQL comprueban que ambos caminos den lo mismo.
 */
final class CalculadoraIndicadores
{
    private const SATISFECHOS = ['SATISFECHO', 'MUY_SATISFECHO'];

    private const LOGRADOS = ['BUENO', 'MUY_BUENO'];

    private const ESTADOS_EGRESADO = ['EGRESADO', 'BACHILLER', 'TITULADO'];

    public function __construct(private readonly IndicadoresService $consultas) {}

    /** Las vistas reflejan el estado actual: solo sirven para un corte de hoy y existen solo en PostgreSQL. */
    public function usaVistas(FiltroIndicadores $filtro): bool
    {
        return DB::getDriverName() === 'pgsql' && $filtro->esCorteDeHoy();
    }

    /** @return array<string, ResultadoIndicador> */
    public function todos(FiltroIndicadores $filtro): array
    {
        $resultados = [];

        foreach (['I1' => 'i1', 'I2' => 'i2', 'I3' => 'i3', 'I4' => 'i4', 'I5' => 'i5', 'I6' => 'i6'] as $codigo => $metodo) {
            try {
                $resultados[$codigo] = $this->{$metodo}($filtro);
            } catch (Throwable $e) {
                report($e);
                $resultados[$codigo] = $this->conError($codigo, $filtro);
            }
        }

        return $resultados;
    }

    /**
     * I1: egresados de la promoción / ingresantes de la promoción. En el total solo entran las promociones que ya cumplieron su
     * duración (ingreso + años de la carrera): las que siguen cursando no tienen aún a todos sus egresados y bajarían el resultado.
     * Al elegir una promoción en curso se muestra su avance con el aviso.
     */
    public function i1(FiltroIndicadores $filtro): ResultadoIndicador
    {
        $todas = $this->i1PorCohorte($filtro);
        $enCurso = array_values(array_filter($todas, fn ($c) => $c['en_curso']));
        $cohortes = $filtro->cohorteId === null ? array_values(array_filter($todas, fn ($c) => ! $c['en_curso'])) : $todas;
        $numerador = (int) array_sum(array_column($cohortes, 'numerador'));
        $denominador = (int) array_sum(array_column($cohortes, 'denominador'));

        $nota = match (true) {
            $enCurso === [] => null,
            $filtro->cohorteId !== null => 'Esta promoción aún está en curso: el resultado es parcial.',
            count($enCurso) === 1 => 'No incluye la promoción '.$enCurso[0]['anio_ingreso'].' (aún en curso).',
            default => 'No incluye las promociones '.implode(', ', array_column($enCurso, 'anio_ingreso')).' (aún en curso).',
        };

        return $this->resultado('I1', $filtro, $denominador > 0 ? ResultadoIndicador::CALCULADO : ResultadoIndicador::SIN_DATOS, [
            'valor' => ResultadoIndicador::porcentaje($numerador, $denominador),
            'numerador' => $numerador,
            'denominador' => $denominador,
            'poblacionElegible' => $denominador,
            'n' => $numerador,
            'nota' => $nota,
        ]);
    }

    /**
     * Una fila por cohorte. Con horizonte se toman las cohortes cuyo egreso esperado cae en la ventana.
     *
     * @return list<array{id_cohorte:int, cohorte:string, etiqueta:string, anio_ingreso:int, en_curso:bool, numerador:int, denominador:int, valor:?float}>
     */
    public function i1PorCohorte(FiltroIndicadores $filtro): array
    {
        $egresados = $this->usaVistas($filtro)
            ? collect($this->consultas->getIndicadorI1($filtro->cohorteId)['data'])->pluck('numerador', 'id_cohorte')
            : $this->egresadosAlCorte($filtro);

        return DB::table('seguimiento_egresado.cohorte')
            ->where('anio_ingreso', '<=', $filtro->corte->year)
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('id', $id))
            ->orderBy('anio_ingreso')
            ->get()
            ->filter(function ($c) use ($filtro) {
                if ($filtro->horizonte === null) {
                    return true;
                }
                $egresoEsperado = $c->anio_ingreso + ($c->duracion_esperada_anios ?? 5);

                return $egresoEsperado >= $filtro->desde()->year && $egresoEsperado <= $filtro->corte->year;
            })
            ->map(function ($c) use ($egresados, $filtro) {
                $numerador = (int) ($egresados[$c->id] ?? 0);

                return [
                    'id_cohorte' => (int) $c->id,
                    'cohorte' => $c->codigo_promocion,
                    'etiqueta' => 'Promoción '.$c->anio_ingreso,
                    'anio_ingreso' => (int) $c->anio_ingreso,
                    'en_curso' => $c->anio_ingreso + ($c->duracion_esperada_anios ?? 5) > $filtro->corte->year,
                    'numerador' => $numerador,
                    'denominador' => (int) $c->total_ingresantes,
                    'valor' => ResultadoIndicador::porcentaje($numerador, (int) $c->total_ingresantes),
                ];
            })
            ->values()
            ->all();
    }

    /** I2: encuestados que trabajan en su carrera / encuestados. No responder no cuenta como desempleo. */
    public function i2(FiltroIndicadores $filtro): ResultadoIndicador
    {
        if ($vista = $this->vistaHorizonte($filtro)) {
            return $this->deEncuesta('I2', $filtro, $vista['invitados'], $vista['trabajan'], $vista['respondieron'], $vista['respondieron']);
        }

        $filas = $this->poblacionEncuesta($filtro);
        $encuestados = $this->ultimasMediciones($filtro, $filas);
        $numerador = $encuestados->filter(fn ($f) => $f->situacion === 'TRABAJA' && (bool) $f->trabajo_relacionado)->count();

        return $this->deEncuesta('I2', $filtro, $filas->unique('id_egresado')->count(), $numerador, $encuestados->count(), $encuestados->count());
    }

    /** I4: satisfechos + muy satisfechos / encuestados con valoración. */
    public function i4(FiltroIndicadores $filtro): ResultadoIndicador
    {
        if ($vista = $this->vistaHorizonte($filtro)) {
            return $this->deEncuesta('I4', $filtro, $vista['invitados'], $vista['satisfechos'], $vista['valoraciones'], $vista['respondieron']);
        }

        $filas = $this->poblacionEncuesta($filtro);
        $encuestados = $this->ultimasMediciones($filtro, $filas);
        $valoradas = $encuestados->whereNotNull('satisfaccion');
        $numerador = $valoradas->whereIn('satisfaccion', self::SATISFECHOS)->count();

        return $this->deEncuesta('I4', $filtro, $filas->unique('id_egresado')->count(), $numerador, $valoradas->count(), $encuestados->count());
    }

    /**
     * I3: objetivos con logro bueno o muy bueno / total de objetivos del programa (no solo los evaluados).
     * Por cohorte se toma su última evaluación validada dentro del filtro.
     */
    public function i3(FiltroIndicadores $filtro): ResultadoIndicador
    {
        $evaluados = $logrados = $total = 0;

        foreach ($this->ultimasEvaluacionesOe($filtro) as $cohorteId => $evaluacion) {
            $evaluados += $evaluacion['evaluados'];
            $logrados += $evaluacion['logrados'];
            $total += DB::table('seguimiento_egresado.objetivo_educacional as o')
                ->join('seguimiento_egresado.cohorte as c', 'c.id_plan_curricular', '=', 'o.id_plan_curricular')
                ->where('c.id', $cohorteId)
                ->where('o.vigente', true)
                ->count();
        }

        if ($evaluados === 0) {
            return $this->resultado('I3', $filtro, ResultadoIndicador::SIN_MEDICION);
        }

        return $this->resultado('I3', $filtro, $total > 0 ? ResultadoIndicador::CALCULADO : ResultadoIndicador::SIN_DATOS, [
            'valor' => ResultadoIndicador::porcentaje($logrados, $total),
            'numerador' => $logrados,
            'denominador' => $total,
            'poblacionElegible' => $total,
            'n' => $evaluados,
            'cobertura' => ResultadoIndicador::porcentaje($evaluados, $total),
        ]);
    }

    /** I5: solo evaluaciones de desempeño vinculadas a un egresado. Las encuestas de mercado nunca cuentan. */
    public function i5(FiltroIndicadores $filtro): ResultadoIndicador
    {
        $filas = DB::table('seguimiento_egresado.invitacion_empresa as ie')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'ie.id_campania')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'ie.id_egresado')
            ->leftJoin('seguimiento_egresado.respuesta_empresa as r', function ($j) use ($filtro) {
                $j->on('r.id_invitacion_empresa', '=', 'ie.id')
                    ->where('r.fecha_respuesta', '<=', $filtro->corteParaConsulta());
            })
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as ns', 'ns.id', '=', 'r.id_nivel_satisfaccion')
            ->where('ie.contexto', 'DESEMPENO')
            ->where('c.fecha_inicio', '<=', $filtro->fechaCorte())
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('e.id_cohorte', $id))
            ->when($filtro->horizonte, fn ($q, $h) => $q->where('c.horizonte_anios', $h))
            ->get(['ie.id', 'ie.id_campania', 'ns.codigo as nivel']);

        // vw_i5_campania no tiene cohorte: con ese filtro se cuenta sobre las filas.
        if ($this->usaVistas($filtro) && $filtro->cohorteId === null) {
            $vista = collect($this->consultas->getIndicadorI5()['data'])->whereIn('id_campania', $filas->pluck('id_campania')->unique());
            $numerador = (int) $vista->sum('numerador');
            $valoradas = (int) $vista->sum('denominador');
        } else {
            $conNivel = $filas->whereNotNull('nivel');
            $numerador = $conNivel->whereIn('nivel', self::SATISFECHOS)->count();
            $valoradas = $conNivel->count();
        }

        return $this->resultado('I5', $filtro, match (true) {
            $filas->isEmpty() => ResultadoIndicador::SIN_DATOS,
            $valoradas === 0 => ResultadoIndicador::SIN_MEDICION,
            default => ResultadoIndicador::CALCULADO,
        }, [
            'valor' => ResultadoIndicador::porcentaje($numerador, $valoradas),
            'numerador' => $numerador,
            'denominador' => $valoradas,
            'poblacionElegible' => $filas->count(),
            'n' => $valoradas,
            'cobertura' => ResultadoIndicador::porcentaje($valoradas, $filas->count()),
        ]);
    }

    /** I6: cantidad absoluta de mejoras implementadas, originadas en el seguimiento y con evidencia. */
    public function i6(FiltroIndicadores $filtro): ResultadoIndicador
    {
        $cantidad = $this->mejorasValidas($filtro)->count();
        $hayRegistros = DB::table('seguimiento_egresado.mejora_curricular')->exists();

        return $this->resultado('I6', $filtro, $hayRegistros ? ResultadoIndicador::CALCULADO : ResultadoIndicador::SIN_DATOS, [
            'valor' => $hayRegistros ? (float) $cantidad : null,
            'numerador' => $hayRegistros ? $cantidad : null,
            'n' => $hayRegistros ? $cantidad : null,
            'nota' => $filtro->cohorteId ? 'Se mide para todo el programa; no depende de la promoción.' : null,
        ]);
    }

    /** @return array<int, int> cantidad de mejoras por año de implementación */
    public function i6PorAnio(FiltroIndicadores $filtro): array
    {
        return $this->mejorasValidas($filtro)
            ->orderBy('fecha_implementacion')
            ->pluck('fecha_implementacion')
            ->countBy(fn ($fecha) => (int) substr((string) $fecha, 0, 4))
            ->all();
    }

    /**
     * Una fila por invitación de seguimiento dentro del filtro; las columnas de respuesta quedan null si no respondió.
     *
     * @return Collection<int, object>
     */
    public function filasEncuesta(FiltroIndicadores $filtro): Collection
    {
        return DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->leftJoin('seguimiento_egresado.medicion_laboral as m', function ($j) use ($filtro) {
                $j->on('m.id_invitacion', '=', 'i.id')
                    ->where('m.fecha_respuesta', '<=', $filtro->corteParaConsulta());
            })
            ->leftJoin('seguimiento_egresado.cat_situacion_laboral as sl', 'sl.id', '=', 'm.id_situacion_laboral')
            ->leftJoin('seguimiento_egresado.satisfaccion_egresado as se', 'se.id_medicion', '=', 'm.id')
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as ns', 'ns.id', '=', 'se.id_nivel_satisfaccion')
            ->leftJoin('seguimiento_egresado.cat_modalidad_laboral as ml', 'ml.id', '=', 'm.id_modalidad_laboral')
            ->leftJoin('seguimiento_egresado.cat_area_profesional as ap', 'ap.id', '=', 'm.id_area_profesional')
            ->leftJoin('seguimiento_egresado.cat_pais as p', 'p.id', '=', 'm.id_pais_residencia')
            ->where('i.proposito', 'SEGUIMIENTO')
            ->where('c.fecha_inicio', '<=', $filtro->fechaCorte())
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('e.id_cohorte', $id))
            ->when($filtro->horizonte, fn ($q, $h) => $q->where('c.horizonte_anios', $h))
            ->get([
                'i.id as id_invitacion', 'i.id_egresado', 'i.id_campania', 'e.fecha_egreso', 'i.estado as estado_invitacion', 'i.expira_en', 'i.entrega_fallida',
                'c.nombre as campania', 'c.periodo', 'c.fecha_fin', 'c.horizonte_anios',
                'm.id as id_medicion', 'm.fecha_respuesta', 'm.trabajo_relacionado',
                'sl.codigo as situacion', 'ns.codigo as satisfaccion',
                'ml.nombre as modalidad', 'ap.nombre as area', 'p.codigo as pais',
            ]);
    }

    /**
     * Una persona con varias campañas cuenta una sola vez, con su respuesta más reciente; el historial no se pierde.
     *
     * @param  Collection<int, object>  $filas
     * @return Collection<int, object>
     */
    public function ultimaRespuestaPorEgresado(Collection $filas): Collection
    {
        return $filas->whereNotNull('id_medicion')
            ->sortByDesc(fn ($f) => [(string) $f->fecha_respuesta, (int) $f->id_medicion])
            ->unique('id_egresado')
            ->values();
    }

    /**
     * Última medición de cada persona con sus datos laborales. Sin horizonte y con corte de hoy sale de
     * vw_ultima_medicion_actual; en los demás casos se obtiene de las filas de la población.
     *
     * @param  Collection<int, object>  $poblacion
     * @return Collection<int, object>
     */
    public function ultimasMediciones(FiltroIndicadores $filtro, Collection $poblacion): Collection
    {
        if (! $this->usaVistas($filtro) || $filtro->horizonte !== null) {
            return $this->ultimaRespuestaPorEgresado($poblacion);
        }

        return DB::table('seguimiento_egresado.vw_ultima_medicion_actual as u')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'u.id_egresado')
            ->leftJoin('seguimiento_egresado.medicion_laboral as m', 'm.id', '=', 'u.id_medicion')
            ->leftJoin('seguimiento_egresado.cat_situacion_laboral as sl', 'sl.id', '=', 'u.id_situacion_laboral')
            ->leftJoin('seguimiento_egresado.satisfaccion_egresado as se', 'se.id_medicion', '=', 'u.id_medicion')
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as ns', 'ns.id', '=', 'se.id_nivel_satisfaccion')
            ->leftJoin('seguimiento_egresado.cat_modalidad_laboral as ml', 'ml.id', '=', 'm.id_modalidad_laboral')
            ->leftJoin('seguimiento_egresado.cat_area_profesional as ap', 'ap.id', '=', 'm.id_area_profesional')
            ->leftJoin('seguimiento_egresado.cat_pais as p', 'p.id', '=', 'u.id_pais_residencia')
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('e.id_cohorte', $id))
            ->get([
                'u.id_egresado', 'u.id_medicion', 'u.fecha_respuesta', 'u.trabajo_relacionado',
                'sl.codigo as situacion', 'ns.codigo as satisfaccion',
                'ml.nombre as modalidad', 'ap.nombre as area', 'p.codigo as pais',
            ]);
    }

    /**
     * Población de los indicadores de encuesta. Con horizonte se toma, por año de egreso, la campaña más reciente
     * de ese horizonte: es la misma agrupación de vw_i2_i4_cohorte_horizonte.
     *
     * @return Collection<int, object>
     */
    public function poblacionEncuesta(FiltroIndicadores $filtro): Collection
    {
        $filas = $this->filasEncuesta($filtro);

        if ($filtro->horizonte === null) {
            return $filas;
        }

        return $filas->groupBy(fn ($f) => (string) self::anio($f->fecha_egreso))
            ->flatMap(function (Collection $delEgreso) {
                $ultimo = $delEgreso->max(fn ($f) => self::anio($f->fecha_fin));

                return $delEgreso->filter(fn ($f) => self::anio($f->fecha_fin) === $ultimo);
            })
            ->values();
    }

    /** @return array{invitados:int, respondieron:int, trabajan:int, valoraciones:int, satisfechos:int}|null */
    private function vistaHorizonte(FiltroIndicadores $filtro): ?array
    {
        // La vista agrupa por año de egreso y no por cohorte: con cohorte se calcula sobre las filas.
        if (! $this->usaVistas($filtro) || $filtro->horizonte === null || $filtro->cohorteId !== null) {
            return null;
        }

        $filas = DB::table('seguimiento_egresado.vw_i2_i4_cohorte_horizonte')->where('horizonte_anios', $filtro->horizonte)->get()
            ->groupBy(fn ($f) => (string) $f->anio_egreso)
            ->flatMap(fn (Collection $delEgreso) => $delEgreso->where('anio_campania', $delEgreso->max('anio_campania')));

        return [
            'invitados' => (int) $filas->sum('invitados'),
            'respondieron' => (int) $filas->sum('respondieron'),
            'trabajan' => (int) $filas->sum('trabajan_en_carrera'),
            'valoraciones' => (int) $filas->sum('valoraciones'),
            'satisfechos' => (int) $filas->sum('satisfechos'),
        ];
    }

    /** Egresados con estado vigente a la fecha de corte, por cohorte. */
    private function egresadosAlCorte(FiltroIndicadores $filtro): Collection
    {
        $corte = $filtro->corteParaConsulta();

        return DB::table('seguimiento_egresado.egresado as e')
            ->join('seguimiento_egresado.historial_estado_academico as h', 'h.id_egresado', '=', 'e.id')
            ->join('seguimiento_egresado.cat_estado_academico as ea', 'ea.id', '=', 'h.id_estado_academico')
            ->whereIn('ea.codigo', self::ESTADOS_EGRESADO)
            ->where('h.vigente_desde', '<=', $corte)
            ->where(fn ($q) => $q->whereNull('h.vigente_hasta')->orWhere('h.vigente_hasta', '>', $corte))
            ->groupBy('e.id_cohorte')
            ->get(['e.id_cohorte', DB::raw('COUNT(DISTINCT e.id) as total')])
            ->pluck('total', 'id_cohorte');
    }

    /**
     * Última evaluación validada de cada cohorte. Sin horizonte y con corte de hoy sale de IndicadoresService (vw_i3_oe).
     *
     * @return array<int, array{periodo:string, evaluados:int, logrados:int}>
     */
    private function ultimasEvaluacionesOe(FiltroIndicadores $filtro): array
    {
        if ($this->usaVistas($filtro) && $filtro->horizonte === null) {
            return collect($this->consultas->getIndicadorI3($filtro->cohorteId)['data'])
                ->sortByDesc('periodo')
                ->unique('id_cohorte')
                ->mapWithKeys(fn ($f) => [(int) $f['id_cohorte'] => ['periodo' => $f['periodo'], 'evaluados' => (int) $f['oe_evaluados'], 'logrados' => (int) $f['numerador']]])
                ->all();
        }

        return DB::table('seguimiento_egresado.evaluacion_oe as ev')
            ->join('seguimiento_egresado.cat_nivel_logro as nl', 'nl.id', '=', 'ev.id_nivel_logro')
            ->where('ev.estado', 'VALIDADA')
            ->where('ev.fecha_evaluacion', '<=', $filtro->fechaCorte())
            ->when($filtro->desde(), fn ($q, $desde) => $q->where('ev.fecha_evaluacion', '>=', $desde->toDateString()))
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('ev.id_cohorte', $id))
            ->orderByDesc('ev.fecha_evaluacion')
            ->get(['ev.id_cohorte', 'ev.periodo', 'nl.codigo as nivel'])
            ->groupBy('id_cohorte')
            ->map(function (Collection $deCohorte) {
                $periodo = $deCohorte->first()->periodo;
                $delPeriodo = $deCohorte->where('periodo', $periodo);

                return ['periodo' => $periodo, 'evaluados' => $delPeriodo->count(), 'logrados' => $delPeriodo->whereIn('nivel', self::LOGRADOS)->count()];
            })
            ->all();
    }

    private static function anio(mixed $fecha): ?int
    {
        return $fecha === null ? null : (int) substr((string) $fecha, 0, 4);
    }

    /**
     * Mejoras que cuenta I6. El contrato exige que estén evidenciadas, por eso se consulta la tabla:
     * vw_i6_anual no comprueba la evidencia.
     */
    public function mejorasValidas(FiltroIndicadores $filtro): Builder
    {
        return DB::table('seguimiento_egresado.mejora_curricular')
            ->where('estado', 'IMPLEMENTADA')
            ->where('origen_seguimiento', true)
            ->whereNotNull('fecha_implementacion')
            ->where(fn ($q) => $q->whereNotNull('id_evidencia')->orWhereNotNull('origen_evidencia')->orWhereNotNull('documento_referencia'))
            ->where('fecha_implementacion', '<=', $filtro->fechaCorte())
            ->when($filtro->desde(), fn ($q, $desde) => $q->where('fecha_implementacion', '>=', $desde->toDateString()));
    }

    private function deEncuesta(string $codigo, FiltroIndicadores $filtro, int $elegibles, int $numerador, int $denominador, int $encuestados): ResultadoIndicador
    {
        return $this->resultado($codigo, $filtro, match (true) {
            $elegibles === 0 => ResultadoIndicador::SIN_DATOS,
            $denominador === 0 => ResultadoIndicador::SIN_MEDICION,
            default => ResultadoIndicador::CALCULADO,
        }, [
            'valor' => ResultadoIndicador::porcentaje($numerador, $denominador),
            'numerador' => $numerador,
            'denominador' => $denominador,
            'poblacionElegible' => $elegibles,
            'n' => $denominador,
            'cobertura' => ResultadoIndicador::porcentaje($encuestados, $elegibles),
        ]);
    }

    /** @param  array<string, mixed>  $datos */
    private function resultado(string $codigo, FiltroIndicadores $filtro, string $estado, array $datos = []): ResultadoIndicador
    {
        $ficha = FichasIndicadores::de($codigo);

        // Sin población o fuente no hay cifras que mostrar: un 0 aquí sería inventado.
        if ($estado === ResultadoIndicador::SIN_DATOS) {
            $datos = array_intersect_key($datos, ['nota' => true]);
        }

        return new ResultadoIndicador(
            codigo: $codigo,
            nombre: $ficha['nombre'],
            formula: $ficha['formula'],
            unidad: $ficha['unidad'],
            fuente: $ficha['fuente'],
            cohorte: $this->etiquetaCohorte($filtro),
            periodo: $filtro->periodo(),
            fechaCorte: $filtro->fechaCorte(),
            estado: $estado,
            valor: $estado === ResultadoIndicador::CALCULADO ? ($datos['valor'] ?? null) : null,
            numerador: $datos['numerador'] ?? null,
            denominador: $datos['denominador'] ?? null,
            poblacionElegible: $datos['poblacionElegible'] ?? null,
            n: $datos['n'] ?? null,
            cobertura: $datos['cobertura'] ?? null,
            nota: $datos['nota'] ?? null,
        );
    }

    private function conError(string $codigo, FiltroIndicadores $filtro): ResultadoIndicador
    {
        $ficha = FichasIndicadores::de($codigo);

        return new ResultadoIndicador(
            codigo: $codigo,
            nombre: $ficha['nombre'],
            formula: $ficha['formula'],
            unidad: $ficha['unidad'],
            fuente: $ficha['fuente'],
            cohorte: $filtro->cohorteId ? 'Promoción elegida' : 'Todas las promociones',
            periodo: $filtro->periodo(),
            fechaCorte: $filtro->fechaCorte(),
            estado: ResultadoIndicador::ERROR,
        );
    }

    private function etiquetaCohorte(FiltroIndicadores $filtro): string
    {
        if ($filtro->cohorteId === null) {
            return 'Todas las promociones';
        }

        $anio = DB::table('seguimiento_egresado.cohorte')->where('id', $filtro->cohorteId)->value('anio_ingreso');

        return $anio === null ? 'Promoción no registrada' : 'Promoción '.$anio;
    }
}
