<?php

namespace App\Shared\Contracts;

interface CierreDemandaPrematricula
{
    public function confirmado(int $periodo): bool;

    public function marcaCierre(int $periodo): ?string;

    public function conBloqueo(callable $operacion): mixed;

    public function cursos(int $periodo): array;
}
