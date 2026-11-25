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
        // EN_CURSO es la transición institucional posterior a PREMATRICULA.
        // No habilitar entre ventanas, antes de abrirlas, ni en periodos archivados.
        return PeriodoAcademico::query()->whereKey($periodo)->where('estado', PeriodosAcademicos::EN_CURSO)->exists()
            && CronogramaPrematricula::query()->where('id_periodo', $periodo)->where('tipo', CronogramaPrematricula::TIPO_ORDINARIA)->exists()
            && ! CronogramaPrematricula::query()->where('id_periodo', $periodo)->where('fecha_fin', '>', now())->exists();
    }

    /** Marca conservadora de la revisión del periodo y de sus ventanas (PostgreSQL local). */
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
