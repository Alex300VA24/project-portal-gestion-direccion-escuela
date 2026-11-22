<?php

namespace App\Modules\PreMatricula\Services;

use App\Modules\PreMatricula\Models\CronogramaPrematricula;
use App\Shared\Models\Core\PeriodoAcademico;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Ventanas de prematrícula (prematricula.cronograma_prematricula).
 * Una ventana está activa desde su inicio (incluido) hasta su fin (excluido).
 */
final class VentanaPrematricula
{
    public const ZONA_HORARIA = 'America/Lima';

    public function __construct(private readonly BloqueoAcademico $bloqueo) {}

    public function activa(int $idPeriodo, ?CarbonInterface $momento = null): ?CronogramaPrematricula
    {
        $momento ??= now();

        return CronogramaPrematricula::query()
            ->where('id_periodo', $idPeriodo)
            ->where('fecha_inicio', '<=', $momento)
            ->where('fecha_fin', '>', $momento)
            ->orderBy('fecha_inicio')
            ->first();
    }

    /** Período con una ventana abierta ahora; null si no hay ninguno. */
    public function periodoHabilitado(): ?PeriodoAcademico
    {
        $ids = CronogramaPrematricula::query()
            ->where('fecha_inicio', '<=', now())
            ->where('fecha_fin', '>', now())
            ->distinct()
            ->pluck('id_periodo');

        if ($ids->count() > 1) {
            throw new PrematriculaException('Hay más de un período con prematrícula abierta. Comunícate con la Dirección de Escuela.');
        }

        return $ids->isEmpty() ? null : PeriodoAcademico::query()->find($ids->first());
    }

    /** @return Collection<int, CronogramaPrematricula> */
    public function delPeriodo(int $idPeriodo): Collection
    {
        return CronogramaPrematricula::query()->where('id_periodo', $idPeriodo)->orderBy('fecha_inicio')->get();
    }

    /**
     * Crea una ventana. Reglas: una sola ORDINARIA por período; una EXTRAORDINARIA
     * exige la ordinaria y empieza después de todas las ventanas previas; no hay solapes.
     * Las fechas llegan en hora de Lima con formato Y-m-d H:i.
     *
     * @param  array{tipo: string, fecha_inicio: string, fecha_fin: string, motivo: string}  $datos
     */
    public function crear(PeriodoAcademico $periodo, array $datos, ?int $idUsuario): CronogramaPrematricula
    {
        return $this->bloqueo->transaccion(function () use ($periodo, $datos, $idUsuario): CronogramaPrematricula {
            $inicio = CarbonImmutable::createFromFormat('Y-m-d H:i', $datos['fecha_inicio'], self::ZONA_HORARIA);
            $fin = CarbonImmutable::createFromFormat('Y-m-d H:i', $datos['fecha_fin'], self::ZONA_HORARIA);

            if ($fin->lte($inicio)) {
                throw ValidationException::withMessages(['fecha_fin' => 'La fecha de cierre debe ser posterior a la de inicio.']);
            }

            $ventanas = $this->delPeriodo($periodo->id_periodo);
            $hayOrdinaria = $ventanas->contains('tipo', CronogramaPrematricula::TIPO_ORDINARIA);

            if ($datos['tipo'] === CronogramaPrematricula::TIPO_ORDINARIA && $hayOrdinaria) {
                throw ValidationException::withMessages(['tipo' => 'El período ya tiene una ventana ordinaria.']);
            }

            if ($datos['tipo'] === CronogramaPrematricula::TIPO_EXTRAORDINARIA
                && (! $hayOrdinaria || $inicio->lt($ventanas->max('fecha_fin')))) {
                throw ValidationException::withMessages(['fecha_inicio' => 'La ventana extraordinaria requiere una ordinaria y debe empezar después de todas las ventanas anteriores.']);
            }

            $solapa = $ventanas->contains(fn (CronogramaPrematricula $ventana): bool => $inicio->lt($ventana->fecha_fin) && $fin->gt($ventana->fecha_inicio));
            if ($solapa) {
                throw ValidationException::withMessages(['fecha_inicio' => 'Las ventanas de un período no pueden solaparse.']);
            }

            return CronogramaPrematricula::query()->create([
                'id_periodo' => $periodo->id_periodo,
                'tipo' => $datos['tipo'],
                'fecha_inicio' => $inicio->utc(),
                'fecha_fin' => $fin->utc(),
                'estado' => 'PROGRAMADA',
                'motivo' => $datos['motivo'],
                'zona_horaria' => self::ZONA_HORARIA,
                'creado_por' => $idUsuario,
            ]);
        });
    }
}
