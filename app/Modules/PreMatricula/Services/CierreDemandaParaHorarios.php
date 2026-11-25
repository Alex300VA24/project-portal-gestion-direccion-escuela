<?php

namespace App\Modules\PreMatricula\Services;

use App\Shared\Contracts\CierreDemandaPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use App\Modules\PreMatricula\Models\CronogramaPrematricula;

final class CierreDemandaParaHorarios implements CierreDemandaPrematricula
{
    public function __construct(private readonly BloqueoAcademico $bloqueo, private readonly FuenteDemandaAgregada $fuente) {}

    public function confirmado(int $periodo): bool
    {
        // Solo si el responsable de Prematrícula acredita expresamente esta política.
        if (config('gestion_horarios_integracion.habilitada') !== true
            || config('gestion_horarios_integracion.cierre_por_transicion') !== true) return false;
        return PeriodoAcademico::query()->whereKey($periodo)->where('estado', PeriodosAcademicos::EN_CURSO)->exists()
            && CronogramaPrematricula::query()->where('id_periodo', $periodo)->where('tipo', CronogramaPrematricula::TIPO_ORDINARIA)->exists()
            && ! CronogramaPrematricula::query()->where('id_periodo', $periodo)->where('fecha_fin', '>', now())->exists()
            && ! app(DemandaAcademica::class)->porCurso($periodo)->contains(fn ($fila) =>
                (int) $fila->valida !== (int) $fila->estudiantes || (int) $fila->pendiente > 0 || (int) $fila->requiere_ajuste > 0);
    }

    /** Marca conservadora PostgreSQL del periodo y sus ventanas; requiere revisión de cierre estable. */
    public function marcaCierre(int $periodo): ?string
    {
        if (! $this->confirmado($periodo)) return null;
        $p = PeriodoAcademico::query()->whereKey($periodo)->selectRaw('xmin::text as revision')->first();
        $ventanas = CronogramaPrematricula::query()->where('id_periodo', $periodo)
            ->orderBy('fecha_inicio')->selectRaw('fecha_inicio, fecha_fin, tipo, xmin::text as revision')->get()->toArray();
        return $p ? hash('sha256', json_encode([$p->revision, $ventanas], JSON_THROW_ON_ERROR)) : null;
    }

    public function conBloqueo(callable $operacion): mixed
    {
        return $this->bloqueo->transaccion(fn () => $operacion());
    }

    public function cursos(int $periodo): array
    {
        return $this->fuente->cursos($periodo);
    }
}
