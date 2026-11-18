<?php

namespace App\Modules\SeguimientoEgresado\Services;

use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use Illuminate\Support\Collection;

/** Listas que necesita el formulario de campaña, en Campañas y en Encuesta empresarial. */
final class CatalogosDeCampania
{
    public const ETAPAS_EGRESADO = ['EGRESADO', 'BACHILLER', 'TITULADO'];

    /**
     * Promociones con cuántos egresados confirmados tienen (solo a ellos se les puede enviar una campaña).
     *
     * @return Collection<int, array{id:int, anio:int, egresados:int}>
     */
    public function promociones(): Collection
    {
        $conteo = Egresado::query()
            ->whereHas('estadoVigente.estadoAcademico', fn ($q) => $q->whereIn('codigo', self::ETAPAS_EGRESADO))
            ->selectRaw('id_cohorte, count(*) as total')->groupBy('id_cohorte')->pluck('total', 'id_cohorte');

        return Cohorte::orderBy('anio_ingreso')->get(['id', 'anio_ingreso'])
            ->map(fn (Cohorte $c) => ['id' => $c->id, 'anio' => $c->anio_ingreso, 'egresados' => (int) ($conteo[$c->id] ?? 0)]);
    }

    /**
     * Periodos académicos para elegir: de dos años atrás a uno adelante, más los que ya usan las campañas existentes.
     *
     * @return Collection<int, string>
     */
    public function periodos(?string $extra = null): Collection
    {
        $anio = (int) now()->year;
        $lista = collect(range($anio + 1, $anio - 2))->flatMap(fn (int $a) => ["{$a}-II", "{$a}-I"]);

        return $lista->merge(CampaniaSeguimiento::query()->distinct()->pluck('periodo'))->when($extra, fn ($c) => $c->push($extra))
            ->filter(fn ($p) => preg_match('/^\d{4}-(I|II)$/', (string) $p))->unique()->sortDesc()->values();
    }
}
