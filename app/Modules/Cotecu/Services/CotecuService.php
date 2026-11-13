<?php

namespace App\Modules\Cotecu\Services;

use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Services\ModuleService;
use Illuminate\Database\Eloquent\Collection;

final class CotecuService extends ModuleService
{
    /**
     * Todos los periodos académicos, del más reciente al más antiguo.
     *
     * @return Collection<int, PeriodoAcademico>
     */
    public function periodos(): Collection
    {
        return PeriodoAcademico::query()
            ->orderByDesc('anio')
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Periodo de contexto de las pantallas: el Activo; si no hay, el más
     * reciente. Así la vista nunca queda sin semestre que mostrar.
     */
    public function periodoActual(): ?PeriodoAcademico
    {
        return PeriodoAcademico::query()
            ->where('estado', 'Activo')
            ->orderByDesc('fecha_inicio')
            ->first()
            ?? $this->periodos()->first();
    }

    /**
     * Solo un periodo Activo habilita la subida de sílabos.
     */
    public function hayPeriodoActivo(): bool
    {
        return PeriodoAcademico::query()->where('estado', 'Activo')->exists();
    }
}
