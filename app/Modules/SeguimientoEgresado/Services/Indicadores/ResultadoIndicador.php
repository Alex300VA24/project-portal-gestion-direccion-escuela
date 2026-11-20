<?php

namespace App\Modules\SeguimientoEgresado\Services\Indicadores;

/**
 * Resultado de un indicador con todos los metadatos del contrato del Sprint 3.
 * valor es null cuando no hay cifra que mostrar: nunca se reemplaza por un cero inventado.
 */
final class ResultadoIndicador
{
    public const CALCULADO = 'CALCULADO';

    public const SIN_DATOS = 'SIN_DATOS';

    public const SIN_MEDICION = 'SIN_MEDICION';

    public const ERROR = 'ERROR';

    public function __construct(
        public readonly string $codigo,
        public readonly string $nombre,
        public readonly string $formula,
        public readonly string $unidad,
        public readonly string $fuente,
        public readonly string $cohorte,
        public readonly string $periodo,
        public readonly string $fechaCorte,
        public readonly string $estado,
        public readonly ?float $valor = null,
        public readonly ?int $numerador = null,
        public readonly ?int $denominador = null,
        public readonly ?int $poblacionElegible = null,
        public readonly ?int $n = null,
        public readonly ?float $cobertura = null,
        public readonly ?string $nota = null,
    ) {}

    public static function porcentaje(int $numerador, int $denominador): ?float
    {
        return $denominador > 0 ? round(100 * $numerador / $denominador, 2) : null;
    }

    public function etiquetaEstado(): string
    {
        return match ($this->estado) {
            self::CALCULADO => 'Calculado',
            self::SIN_DATOS => 'Sin datos',
            self::SIN_MEDICION => 'Sin medición',
            default => 'Error',
        };
    }

    public function explicacionEstado(): ?string
    {
        return match ($this->estado) {
            self::SIN_DATOS => 'No hay población o fuente registrada para este filtro.',
            self::SIN_MEDICION => 'Aún no existen respuestas o evaluaciones para este filtro.',
            self::ERROR => 'No se pudo consultar la base de datos.',
            default => null,
        };
    }

    /** @return array<string, mixed> */
    public function aArreglo(): array
    {
        return [
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'formula' => $this->formula,
            'valor' => $this->valor,
            'unidad' => $this->unidad,
            'numerador' => $this->numerador,
            'denominador' => $this->denominador,
            'poblacion_elegible' => $this->poblacionElegible,
            'n' => $this->n,
            'cobertura' => $this->cobertura,
            'cohorte' => $this->cohorte,
            'periodo' => $this->periodo,
            'fecha_corte' => $this->fechaCorte,
            'fuente' => $this->fuente,
            'estado' => $this->estado,
            'nota' => $this->nota,
        ];
    }
}
