<?php

namespace Tests\Feature\GestionHorarios\soporte;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;

/** Disponible exclusivamente para pruebas; nunca se registra en el contenedor de la aplicación. */
final class ProveedorEscuelasFicticio implements ProveedorEscuelasAutorizadas
{
    /** @param array<int, list<int>> $adscripciones */
    public function __construct(private array $adscripciones) {}

    public function escuelasVigentes(User $usuario): array
    {
        return $this->adscripciones[(int) $usuario->getKey()] ?? [];
    }

    public function revocar(int $usuario): void
    {
        unset($this->adscripciones[$usuario]);
    }
}
