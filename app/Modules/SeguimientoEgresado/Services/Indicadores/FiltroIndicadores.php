<?php

namespace App\Modules\SeguimientoEgresado\Services\Indicadores;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Filtros del tablero y de la exportación: cohorte, horizonte (3/5/10 años) y fecha de corte.
 * Un mismo filtro produce las mismas cifras en el tablero, el CSV y los reportes.
 */
final class FiltroIndicadores
{
    public const HORIZONTES = [3, 5, 10];

    /** Las fechas que ve el usuario se muestran en hora de Perú. */
    public const ZONA = 'America/Lima';

    public readonly CarbonImmutable $corte;

    public function __construct(
        public readonly ?int $cohorteId = null,
        public readonly ?int $horizonte = null,
        ?CarbonImmutable $corte = null,
    ) {
        $this->corte = ($corte ?? CarbonImmutable::now(self::ZONA))->endOfDay();
    }

    /** Los valores no válidos se ignoran; una fecha futura se limita a hoy. */
    public static function desdeConsulta(array $consulta): self
    {
        $cohorte = filter_var($consulta['cohorte'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $horizonte = filter_var($consulta['horizonte'] ?? null, FILTER_VALIDATE_INT);

        return new self(
            $cohorte === false ? null : $cohorte,
            in_array($horizonte, self::HORIZONTES, true) ? $horizonte : null,
            self::fechaDeCorte($consulta['corte'] ?? null),
        );
    }

    public function conHorizonte(?int $horizonte): self
    {
        return new self($this->cohorteId, $horizonte, $this->corte);
    }

    /** Inicio de la ventana de tiempo; null cuando se consulta todo el histórico. */
    public function desde(): ?CarbonImmutable
    {
        return $this->horizonte === null ? null : $this->corte->subYears($this->horizonte)->startOfDay();
    }

    public function periodo(): string
    {
        return $this->horizonte === null
            ? 'Histórico hasta '.$this->corte->year
            : 'Últimos '.$this->horizonte.' años ('.$this->desde()->year.'–'.$this->corte->year.')';
    }

    public function fechaCorte(): string
    {
        return $this->corte->toDateString();
    }

    /** Instante del corte en la zona con la que la aplicación guarda los datos. */
    public function corteParaConsulta(): string
    {
        return $this->corte->setTimezone(config('app.timezone'))->format('Y-m-d H:i:sP');
    }

    public function esCorteDeHoy(): bool
    {
        return $this->corte->isSameDay(CarbonImmutable::now(self::ZONA));
    }

    private static function fechaDeCorte(mixed $valor): ?CarbonImmutable
    {
        if (! is_string($valor) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return null;
        }

        try {
            $fecha = CarbonImmutable::createFromFormat('!Y-m-d', $valor, self::ZONA);
        } catch (Throwable) {
            return null;
        }

        return $fecha === null || $fecha->isFuture() ? null : $fecha;
    }
}
