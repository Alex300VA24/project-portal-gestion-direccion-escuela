<?php

namespace App\Shared\Contracts;

/** Captura institucional completa por periodo (no nominal), nunca parcial por escuela. */
interface FuenteDemandaPrematricula
{
    /** @return list<array{id_curso_plan: int, total: int, manana: int, tarde: int}> */
    public function cursos(int $periodo): array;
}
