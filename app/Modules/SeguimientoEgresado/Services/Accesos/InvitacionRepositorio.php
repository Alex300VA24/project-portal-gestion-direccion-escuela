<?php

namespace App\Modules\SeguimientoEgresado\Services\Accesos;

use Illuminate\Container\Attributes\Bind;

#[Bind(InvitacionDb::class)]
interface InvitacionRepositorio
{
    /** Busca por token_hash (SHA-256 en hexadecimal). El token en claro nunca se guarda. */
    public function buscarPorHash(string $hash): ?InvitacionAcceso;
}
