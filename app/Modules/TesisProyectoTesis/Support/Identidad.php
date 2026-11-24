<?php

namespace App\Modules\TesisProyectoTesis\Support;

/**
 * Identidad académica del usuario autenticado (contrato con core/seguridad).
 */
final class Identidad
{
    public function __construct(
        public readonly int $idUsuario,
        public readonly int $idPersona,
        public readonly string $nombre,
        public readonly ?int $idEstudiante,
        public readonly ?int $idDocente,
    ) {}

    public function iniciales(): string
    {
        $partes = preg_split('/\s+/', trim($this->nombre)) ?: [];

        return mb_strtoupper(mb_substr($partes[0] ?? '', 0, 1).mb_substr($partes[count($partes) > 2 ? 2 : 1] ?? '', 0, 1));
    }
}
