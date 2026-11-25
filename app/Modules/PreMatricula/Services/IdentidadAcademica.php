<?php

namespace App\Modules\PreMatricula\Services;

use App\Models\User;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Seguridad\Usuario;

/**
 * Relaciona la cuenta del portal (public.users) con la identidad académica.
 *
 * Ruta acordada: users.email = seguridad.usuario.correo_institucional,
 * y seguridad.usuario.id_persona = core.estudiante.id_persona.
 */
final class IdentidadAcademica
{
    public function usuario(User $user): ?Usuario
    {
        return Usuario::query()
            ->whereRaw('lower(correo_institucional) = ?', [mb_strtolower($user->email)])
            ->first();
    }

    public function idUsuario(User $user): ?int
    {
        return $this->usuario($user)?->id_usuario;
    }

    public function idUsuarioObligatorio(User $user): int
    {
        return $this->idUsuario($user) ?? throw new PrematriculaException(
            'Tu cuenta no está vinculada a un usuario institucional. Solicita a la Dirección que registre el correo '.$user->email.'.'
        );
    }

    public function estudiante(User $user): ?Estudiante
    {
        $usuario = $this->usuario($user);

        if ($usuario === null) {
            return null;
        }

        return Estudiante::query()
            ->with('persona')
            ->where('id_persona', $usuario->id_persona)
            ->orderByRaw("CASE WHEN upper(estado) = 'ACTIVO' THEN 0 ELSE 1 END")
            ->orderByDesc('id_estudiante')
            ->first();
    }
}
