<?php

namespace App\Modules\GestionHorarios\Contracts;

use App\Modules\GestionHorarios\Demand\InstantaneaDemanda;

interface RepositorioDemanda
{
    /** Ejecuta la operación en una transacción con exclusión mutua para el periodo, incluso si no existe una versión previa. */
    public function conBloqueo(int $periodo, callable $operacion): mixed;

    /** @return array{version: int, hash: string}|null */
    public function ultima(int $periodo): ?array;

    /** Persiste la instantánea y su versión atómicamente; nunca modifica horarios existentes. */
    public function guardar(InstantaneaDemanda $instantanea): void;
}
