<?php

namespace App\Modules\SeguimientoEgresado\Services\Accesos;

use Carbon\CarbonImmutable;

/** Datos mínimos de invitacion_egresado y del titular que se necesitan para decidir un acceso. */
final class InvitacionAcceso
{
    public function __construct(
        public readonly int $id,
        public readonly int $idEgresado,
        public readonly ?int $idCampania,
        public readonly string $proposito,
        public readonly string $estado,
        public readonly CarbonImmutable $expiraEn,
        public readonly string $codigoMatricula,
        public readonly ?string $numeroDocumento,
    ) {}
}
