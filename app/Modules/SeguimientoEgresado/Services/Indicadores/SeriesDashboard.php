<?php

namespace App\Modules\SeguimientoEgresado\Services\Indicadores;

use App\Modules\SeguimientoEgresado\Services\IndicadoresService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Datos para los gráficos del tablero; usan la misma base de cálculo que las tarjetas de I1–I6. */
final class SeriesDashboard
{
    private const SATISFECHOS = ['SATISFECHO', 'MUY_SATISFECHO'];

    public function __construct(
        private readonly CalculadoraIndicadores $calculadora,
        private readonly IndicadoresService $consultas,
    ) {}

    /**
     * Tareas de Secretaría: pendientes de importación, cohortes con personas sin contacto, campañas activas y cobertura.
     *
     * @return array{importaciones_pendientes:int, sin_contacto:int, cohortes_sin_contacto:int, campanias_activas:int, invitados_activas:int, respondieron_activas:int, cobertura_activas:?float}
     */
    public function gestion(FiltroIndicadores $filtro): array
    {
        $sinContacto = DB::table('seguimiento_egresado.egresado as e')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('seguimiento_egresado.contacto_egresado as ce')->whereColumn('ce.id_egresado', 'e.id')->whereNull('ce.vigente_hasta'))
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('e.id_cohorte', $id));

        $activas = DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'i.id_campania')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->leftJoin('seguimiento_egresado.medicion_laboral as m', 'm.id_invitacion', '=', 'i.id')
            ->where('c.estado', 'ABIERTA')
            ->where('i.proposito', 'SEGUIMIENTO')
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('e.id_cohorte', $id))
            ->get(['i.id', 'm.id as id_medicion']);
        $respondieron = $activas->whereNotNull('id_medicion')->count();

        return [
            'importaciones_pendientes' => DB::table('seguimiento_egresado.importacion_padron')->whereIn('estado', ['EN_PROCESO', 'CON_ERRORES'])->count(),
            'sin_contacto' => (clone $sinContacto)->count(),
            'cohortes_sin_contacto' => (clone $sinContacto)->distinct()->count('e.id_cohorte'),
            'campanias_activas' => DB::table('seguimiento_egresado.campania_seguimiento')->where('estado', 'ABIERTA')->count(),
            'invitados_activas' => $activas->count(),
            'respondieron_activas' => $respondieron,
            'cobertura_activas' => ResultadoIndicador::porcentaje($respondieron, $activas->count()),
        ];
    }

    /**
     * Resultados de Dirección: logro por objetivo y mejoras con su evidencia.
     *
     * @return array{objetivos:list<array<string,mixed>>, mejoras:list<array<string,mixed>>}
     */
    public function direccion(FiltroIndicadores $filtro): array
    {
        $objetivos = DB::table('seguimiento_egresado.evaluacion_oe as ev')
            ->join('seguimiento_egresado.objetivo_educacional as o', 'o.id', '=', 'ev.id_objetivo')
            ->join('seguimiento_egresado.cat_nivel_logro as nl', 'nl.id', '=', 'ev.id_nivel_logro')
            ->where('ev.estado', 'VALIDADA')
            ->where('ev.fecha_evaluacion', '<=', $filtro->fechaCorte())
            ->when($filtro->desde(), fn ($q, $desde) => $q->where('ev.fecha_evaluacion', '>=', $desde->toDateString()))
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('ev.id_cohorte', $id))
            ->orderByDesc('ev.fecha_evaluacion')
            ->get(['ev.id_cohorte', 'ev.periodo', 'o.codigo', 'o.titulo', 'nl.codigo as nivel'])
            // Igual que I3: de cada promoción cuenta su último informe validado.
            ->pipe(fn (Collection $filas) => $filas->filter(fn ($f) => $f->periodo === $filas->firstWhere('id_cohorte', $f->id_cohorte)->periodo))
            ->sortBy('codigo')
            ->groupBy('codigo')
            ->map(fn (Collection $filas, string $codigo) => [
                'codigo' => $codigo,
                'titulo' => $filas->first()->titulo,
                'evaluaciones' => $filas->count(),
                'logradas' => $filas->whereIn('nivel', ['BUENO', 'MUY_BUENO'])->count(),
            ])
            ->values()
            ->all();

        $mejoras = $this->calculadora->mejorasValidas($filtro)
            ->orderByDesc('fecha_implementacion')
            ->get(['codigo', 'titulo', 'fecha_implementacion', 'documento_referencia', 'origen_evidencia'])
            ->map(fn ($m) => [
                'codigo' => $m->codigo,
                'titulo' => $m->titulo,
                'fecha' => $m->fecha_implementacion,
                'evidencia' => $m->documento_referencia ?? $m->origen_evidencia ?? 'Archivo de evidencia registrado',
            ])
            ->all();

        return ['objetivos' => $objetivos, 'mejoras' => $mejoras];
    }

    /** @return array{registrados:int, contactables:int, campanias_abiertas:int, ultima_respuesta:?string} */
    public function resumen(FiltroIndicadores $filtro): array
    {
        $egresados = DB::table('seguimiento_egresado.egresado')->when($filtro->cohorteId, fn ($q, $id) => $q->where('id_cohorte', $id));

        $contactables = DB::table('seguimiento_egresado.contacto_egresado as ce')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'ce.id_egresado')
            ->whereNull('ce.vigente_hasta')
            ->when($filtro->cohorteId, fn ($q, $id) => $q->where('e.id_cohorte', $id))
            ->distinct()
            ->count('ce.id_egresado');

        $ultima = $this->calculadora->filasEncuesta($filtro)->max('fecha_respuesta');

        return [
            'registrados' => (clone $egresados)->count(),
            'contactables' => $contactables,
            'campanias_abiertas' => DB::table('seguimiento_egresado.campania_seguimiento')->where('estado', 'ABIERTA')->count(),
            'ultima_respuesta' => $ultima ? CarbonImmutable::parse($ultima)->toDateString() : null,
        ];
    }

    /**
     * Una fila por campaña, sin deduplicar: es lo que se contrasta con vw_i2_campania, vw_i4_campania y vw_cobertura_campania.
     *
     * @return list<array<string, mixed>>
     */
    public function campanias(FiltroIndicadores $filtro): array
    {
        return $this->calculadora->filasEncuesta($filtro)
            ->groupBy('id_campania')
            ->map(function (Collection $filas) use ($filtro) {
                $primera = $filas->first();
                $respondieron = $filas->whereNotNull('id_medicion');
                $sinResponder = $filas->whereNull('id_medicion');
                $vencidas = $sinResponder->filter(fn ($f) => in_array($f->estado_invitacion, ['EXPIRADA', 'ANULADA'], true)
                    || CarbonImmutable::parse($f->expira_en)->lessThanOrEqualTo($filtro->corte))->count();
                $trabajan = $respondieron->filter(fn ($f) => $f->situacion === 'TRABAJA' && (bool) $f->trabajo_relacionado)->count();
                $valoradas = $respondieron->whereNotNull('satisfaccion')->count();
                $satisfechos = $respondieron->whereIn('satisfaccion', self::SATISFECHOS)->count();

                return [
                    'id_campania' => (int) $primera->id_campania,
                    'campania' => $primera->campania,
                    'periodo' => $primera->periodo,
                    'fecha_fin' => $primera->fecha_fin,
                    'horizonte' => $primera->horizonte_anios === null ? null : (int) $primera->horizonte_anios,
                    'invitados' => $filas->count(),
                    'respondieron' => $respondieron->count(),
                    'pendientes' => $sinResponder->count() - $vencidas,
                    'vencidas' => $vencidas,
                    'entregas_fallidas' => $filas->filter(fn ($f) => (bool) $f->entrega_fallida)->count(),
                    'cobertura' => ResultadoIndicador::porcentaje($respondieron->count(), $filas->count()),
                    'trabajan_en_carrera' => $trabajan,
                    'i2' => ResultadoIndicador::porcentaje($trabajan, $respondieron->count()),
                    'valoraciones' => $valoradas,
                    'satisfechos' => $satisfechos,
                    'i4' => ResultadoIndicador::porcentaje($satisfechos, $valoradas),
                ];
            })
            ->sortBy('fecha_fin')
            ->values()
            ->map(fn (array $c) => $this->conVistasDeCampania($c, $filtro))
            ->all();
    }

    /**
     * Con corte de hoy y sin cohorte, las cifras por campaña salen de IndicadoresService (vw_i2, vw_i4 y vw_cobertura).
     *
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function conVistasDeCampania(array $c, FiltroIndicadores $filtro): array
    {
        if (! $this->calculadora->usaVistas($filtro) || $filtro->cohorteId !== null) {
            return $c;
        }

        $i2 = collect($this->consultas->getIndicadorI2($c['id_campania'])['data'])->first();
        $i4 = collect($this->consultas->getIndicadorI4($c['id_campania'])['data'])->first();
        $cobertura = collect($this->consultas->getCoberturaCampania($c['id_campania']))->first();

        if ($i2) {
            $c['trabajan_en_carrera'] = $i2['numerador'];
            $c['respondieron'] = $i2['denominador'];
            $c['i2'] = $i2['resultado'];
        }
        if ($i4) {
            $c['satisfechos'] = $i4['numerador'];
            $c['valoraciones'] = $i4['denominador'];
            $c['i4'] = $i4['resultado'];
        }
        if ($cobertura) {
            $c['invitados'] = (int) $cobertura->invitados;
            $c['cobertura'] = $cobertura->cobertura_pct === null ? null : (float) $cobertura->cobertura_pct;
            $c['entregas_fallidas'] = (int) $cobertura->entregas_fallidas;
        }

        return $c;
    }

    /**
     * Evolución de I2 e I4 por periodo de campaña, ordenada por fecha real. Solo periodos con respuestas.
     *
     * @return list<array{periodo:string, n:int, i2:?float, i4:?float}>
     */
    public function tendencia(FiltroIndicadores $filtro): array
    {
        return collect($this->campanias($filtro))
            ->groupBy('periodo')
            ->map(function (Collection $campanias, string $periodo) {
                $n = (int) $campanias->sum('respondieron');

                return [
                    'periodo' => $periodo,
                    'fecha' => $campanias->max('fecha_fin'),
                    'n' => $n,
                    'i2' => ResultadoIndicador::porcentaje((int) $campanias->sum('trabajan_en_carrera'), $n),
                    'i4' => ResultadoIndicador::porcentaje((int) $campanias->sum('satisfechos'), (int) $campanias->sum('valoraciones')),
                ];
            })
            ->filter(fn ($p) => $p['n'] > 0)
            ->sortBy('fecha')
            ->values()
            ->all();
    }

    /**
     * Comparación de I2 e I4 entre horizontes de 3, 5 y 10 años, respetando la cohorte y el corte.
     *
     * @return list<array{horizonte:int, i2:ResultadoIndicador, i4:ResultadoIndicador}>
     */
    public function horizontes(FiltroIndicadores $filtro): array
    {
        return array_map(function (int $h) use ($filtro) {
            $conHorizonte = $filtro->conHorizonte($h);

            return ['horizonte' => $h, 'i2' => $this->calculadora->i2($conHorizonte), 'i4' => $this->calculadora->i4($conHorizonte)];
        }, FiltroIndicadores::HORIZONTES);
    }

    /**
     * Situación laboral según la respuesta más reciente de cada persona.
     *
     * @return array{n:int, trabajan:int, cobertura:?float, modalidades:array<string,int>, areas:array<string,int>, ubicacion:array<string,int>}
     */
    public function laboral(FiltroIndicadores $filtro): array
    {
        $poblacion = $this->calculadora->poblacionEncuesta($filtro);
        $ultimas = $this->calculadora->ultimasMediciones($filtro, $poblacion);
        $trabajan = $ultimas->where('situacion', 'TRABAJA');

        return [
            'n' => $ultimas->count(),
            'cobertura' => ResultadoIndicador::porcentaje($ultimas->count(), $poblacion->unique('id_egresado')->count()),
            'trabajan' => $trabajan->count(),
            'modalidades' => $trabajan->whereNotNull('modalidad')->countBy('modalidad')->sortDesc()->all(),
            'areas' => $trabajan->whereNotNull('area')->countBy('area')->sortDesc()->take(6)->all(),
            'ubicacion' => [
                'Perú' => $ultimas->where('pais', 'PE')->count(),
                'Extranjero' => $ultimas->whereNotNull('pais')->where('pais', '!=', 'PE')->count(),
            ],
        ];
    }
}
