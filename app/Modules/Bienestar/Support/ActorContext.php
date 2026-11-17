<?php

namespace App\Modules\Bienestar\Support;

final readonly class ActorContext
{
    public function __construct(
        public ?int $idUsuario,
        public ?int $idPersona,
        public ?int $idEstudiante,
        public ?string $codigoEstudiante,
        public ?int $idDocente,
        public ?string $codigoDocente,
        public ?string $role,
        public string $nombreCompleto,
        public string $email,
    ) {}

    public function isAlumno(): bool
    {
        return $this->role === 'alumno';
    }

    public function isProfesor(): bool
    {
        return $this->role === 'profesor';
    }

    public function isSecretaria(): bool
    {
        return $this->role === 'secretaria';
    }

    public function isDirector(): bool
    {
        return $this->role === 'director_escuela';
    }
}
