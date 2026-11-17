<?php

namespace App\Modules\SeguimientoEgresado\Services\Empresas;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Resultados agregados de la encuesta empresarial para Secretaría y Director. */
final class ResultadosEmpresas
{
    private const SATISFECHOS = ['SATISFECHO', 'MUY_SATISFECHO'];

    /** @return array<string, mixed> */
    public function resumen(?int $campaniaId = null, ?string $contexto = null): array
    {
        $invitaciones = $this->invitaciones($campaniaId, $contexto);
        $respondidas = $invitaciones->where('estado_real', 'COMPLETADA')->count();
        $desempeno = $invitaciones->filter(fn ($i) => $i->contexto === 'DESEMPENO' && $i->id_egresado !== null);
        $valoradas = $desempeno->whereNotNull('nivel_codigo');
        $satisfechas = $valoradas->whereIn('nivel_codigo', self::SATISFECHOS)->count();

        return [
            'invitaciones' => $invitaciones,
            'total' => $invitaciones->count(),
            'respondidas' => $respondidas,
            'pendientes' => $invitaciones->where('estado_real', 'PENDIENTE')->count(),
            'vencidas' => $invitaciones->where('estado_real', 'EXPIRADA')->count(),
            'cobertura' => self::porcentaje($respondidas, $invitaciones->count()),
            'mercado' => $invitaciones->where('contexto', 'MERCADO')->where('estado_real', 'COMPLETADA')->count(),
            'desempeno' => $valoradas->count(),
            'satisfechas' => $satisfechas,
            'i5' => self::porcentaje($satisfechas, $valoradas->count()),
            'demandas' => $this->ranking('DEMANDA', $campaniaId, $contexto),
            'brechas' => $this->ranking('BRECHA', $campaniaId, $contexto),
            'satisfaccion' => $this->distribucionSatisfaccion($valoradas),
        ];
    }

    /** @return Collection<int, object> */
    public function campaniasDeEmpresas(): Collection
    {
        return DB::table('seguimiento_egresado.campania_seguimiento')
            ->whereIn('tipo', ['EMPRESAS', 'MIXTA'])
            ->orderByDesc('fecha_fin')
            ->get(['id', 'nombre', 'periodo', 'estado', 'fecha_fin']);
    }

    /**
     * Egresados cuya última respuesta autoriza contactar a su empresa. Solo se usa en el formulario interno.
     *
     * @return Collection<int, object>
     */
    public function egresadosEvaluables(): Collection
    {
        $filas = DB::table('seguimiento_egresado.medicion_laboral as m')
            ->join('seguimiento_egresado.invitacion_egresado as i', 'i.id', '=', 'm.id_invitacion')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->join('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->orderByDesc('m.fecha_respuesta')
            ->orderByDesc('m.id')
            ->get([
                'e.id', 'e.nombres', 'e.apellido_paterno', 'e.apellido_materno', 'c.codigo_promocion',
                'm.nombre_empleador_cliente', 'm.autoriza_contacto_empresa',
            ]);

        return $filas->unique('id')
            ->filter(fn ($f) => (bool) $f->autoriza_contacto_empresa)
            ->sortBy(fn ($f) => $f->apellido_paterno.' '.$f->nombres)
            ->values();
    }

    /** @return Collection<int, object> */
    private function invitaciones(?int $campaniaId, ?string $contexto): Collection
    {
        $ahora = CarbonImmutable::now();

        return DB::table('seguimiento_egresado.invitacion_empresa as ie')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'ie.id_campania')
            ->leftJoin('seguimiento_egresado.respuesta_empresa as r', 'r.id_invitacion_empresa', '=', 'ie.id')
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as ns', 'ns.id', '=', 'r.id_nivel_satisfaccion')
            ->leftJoin('seguimiento_egresado.cat_sector_empresa as se', 'se.id', '=', 'r.id_sector_empresa')
            ->when($campaniaId, fn ($q) => $q->where('ie.id_campania', $campaniaId))
            ->when($contexto, fn ($q) => $q->where('ie.contexto', $contexto))
            ->orderByDesc('ie.id')
            ->get([
                'ie.id', 'ie.contexto', 'ie.id_egresado', 'ie.organizacion', 'ie.estado', 'ie.expira_en', 'ie.enviado_en', 'ie.respondido_en',
                'c.nombre as campania', 'c.periodo', 'c.estado as estado_campania', 'r.organizacion_informada', 'ns.codigo as nivel_codigo', 'ns.nombre as nivel_nombre', 'se.nombre as sector',
            ])
            ->map(function ($i) use ($ahora) {
                $i->estado_real = $i->estado === 'PENDIENTE' && CarbonImmutable::parse($i->expira_en)->lessThanOrEqualTo($ahora)
                    ? 'EXPIRADA'
                    : $i->estado;

                return $i;
            });
    }

    /** @return list<array{codigo:string, nombre:string, cantidad:int, porcentaje:?float, base:int}> */
    private function ranking(string $tipo, ?int $campaniaId, ?string $contexto): array
    {
        $base = DB::table('seguimiento_egresado.respuesta_empresa as r')
            ->join('seguimiento_egresado.invitacion_empresa as ie', 'ie.id', '=', 'r.id_invitacion_empresa')
            ->when($campaniaId, fn ($q) => $q->where('ie.id_campania', $campaniaId))
            ->when($contexto, fn ($q) => $q->where('ie.contexto', $contexto))
            ->count();

        return DB::table('seguimiento_egresado.respuesta_empresa_habilidad as rh')
            ->join('seguimiento_egresado.respuesta_empresa as r', 'r.id', '=', 'rh.id_respuesta')
            ->join('seguimiento_egresado.invitacion_empresa as ie', 'ie.id', '=', 'r.id_invitacion_empresa')
            ->join('seguimiento_egresado.cat_habilidad as h', 'h.id', '=', 'rh.id_habilidad')
            ->where('rh.tipo', $tipo)
            ->when($campaniaId, fn ($q) => $q->where('ie.id_campania', $campaniaId))
            ->when($contexto, fn ($q) => $q->where('ie.contexto', $contexto))
            ->groupBy('h.codigo', 'h.nombre')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->orderBy('h.nombre')
            ->limit(8)
            ->get(['h.codigo', 'h.nombre', DB::raw('COUNT(*) as cantidad')])
            ->map(fn ($f) => [
                'codigo' => $f->codigo,
                'nombre' => $f->nombre,
                'cantidad' => (int) $f->cantidad,
                'porcentaje' => self::porcentaje((int) $f->cantidad, $base),
                'base' => $base,
            ])
            ->all();
    }

    /** @return list<array{nombre:string, cantidad:int}> */
    private function distribucionSatisfaccion(Collection $valoradas): array
    {
        return DB::table('seguimiento_egresado.cat_nivel_satisfaccion')->orderBy('id')->get(['codigo', 'nombre'])
            ->map(fn ($n) => ['nombre' => $n->nombre, 'cantidad' => $valoradas->where('nivel_codigo', $n->codigo)->count()])
            ->all();
    }

    private static function porcentaje(int $numerador, int $denominador): ?float
    {
        return $denominador > 0 ? round(100 * $numerador / $denominador, 2) : null;
    }
}
