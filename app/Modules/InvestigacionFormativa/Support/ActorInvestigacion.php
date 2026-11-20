<?php

namespace App\Modules\InvestigacionFormativa\Support;

use App\Models\User;

final readonly class ActorInvestigacion
{
    /**
     * @param  list<string>  $roles
     */
    public function __construct(
        public User $user,
        public int $idUsuarioSeguridad,
        public int $idPersona,
        public ?int $idDocente,
        public array $roles,
    ) {}

    public function tieneRol(string ...$roles): bool
    {
        return array_intersect($roles, $this->roles) !== [];
    }
}
