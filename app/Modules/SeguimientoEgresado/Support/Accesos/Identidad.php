<?php

namespace App\Modules\SeguimientoEgresado\Support\Accesos;

use Carbon\CarbonImmutable;

/**
 * Quién está usando el módulo y qué alcance tiene.
 * El egresado queda atado a su expediente y a la invitación con la que entró; Secretaría y Dirección a su cuenta
 * interna del módulo (que se enlaza con la cuenta del portal).
 */
final class Identidad
{
    public function __construct(
        public readonly Perfil $perfil,
        public readonly ?int $usuarioId = null,
        public readonly ?int $egresadoId = null,
        public readonly ?int $invitacionId = null,
        public readonly ?CarbonImmutable $venceEn = null,
    ) {}

    public function puede(string $capacidad): bool
    {
        return Capacidades::permite($this->perfil, $capacidad);
    }

    public function haVencido(): bool
    {
        return $this->venceEn !== null && $this->venceEn->lessThanOrEqualTo(CarbonImmutable::now());
    }
}
