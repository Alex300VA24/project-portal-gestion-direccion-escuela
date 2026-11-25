<?php

namespace App\Shared\Contracts;

/** Captura completa por periodo (no nominal); alcance de una escuela en la prueba local. */
interface FuenteDemandaPrematricula
{
    /** @return list<array{id_curso_plan: int, total: int, manana: int, tarde: int}> */
    public function cursos(int $periodo): array;
}
