<?php

namespace App\Modules\Bienestar\DTO;

final readonly class RegistroAcademicoDTO
{
    public function __construct(
        public string $codigoEstudiante,
        public string $periodo,
        public string $curso,
        public int $vecesCursado,
        public float $porcentajeInasistencia,
        public int $creditosMatriculadosPeriodo,
        public ?int $creditosPendientesEgreso = null,
        public ?float $promedioPonderado = null,
    ) {}
}
