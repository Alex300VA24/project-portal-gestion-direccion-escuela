<?php

namespace App\Modules\GestionPracticas\Support;

use App\Models\User;
use App\Shared\Models\Core\Estudiante;
use App\Shared\Models\Seguridad\Usuario;
use Illuminate\Support\Facades\DB;

/**
 * Identidad del usuario autenticado dentro del módulo.
 *
 * El inicio de sesión (Breeze) usa public.users, mientras que el dominio guarda quién
 * actúa en seguridad.usuario / core.estudiante / core.docente / empresa_usuario.
 * El vínculo es el correo: public.users.email = seguridad.usuario.correo_institucional.
 */
final class Actor
{
    public function __construct(
        public readonly User $user,
        public readonly ?int $usuarioId,
        public readonly ?int $personaId,
        public readonly ?int $estudianteId,
        public readonly ?int $docenteId,
        public readonly ?int $empresaId,
    ) {}

    /** Actor del usuario autenticado en la petición actual. */
    public static function actual(): self
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 401);

        return self::de($user);
    }

    public static function de(User $user): self
    {
        $cache = request()->attributes;
        $clave = 'gestion-practicas.actor.'.$user->getKey();

        if ($cache->has($clave)) {
            return $cache->get($clave);
        }

        $usuario = Usuario::query()
            ->whereRaw('lower(correo_institucional) = ?', [mb_strtolower($user->email)])
            ->first(['id_usuario', 'id_persona']);

        $personaId = $usuario?->id_persona;
        $usuarioId = $usuario?->id_usuario;

        $estudiante = $personaId === null ? null : Estudiante::query()
            ->where('id_persona', $personaId)->orderBy('id_estudiante')->value('id_estudiante');
        $docente = $personaId === null ? null : DB::table('core.docente')
            ->where('id_persona', $personaId)->value('id_docente');
        $empresa = $usuarioId === null ? null : DB::table('gestion_practicas.empresa_usuario')
            ->where('id_usuario', $usuarioId)->where('estado', 'activo')->value('id_empresa');

        $actor = new self(
            $user,
            $usuarioId === null ? null : (int) $usuarioId,
            $personaId === null ? null : (int) $personaId,
            $estudiante === null ? null : (int) $estudiante,
            $docente === null ? null : (int) $docente,
            $empresa === null ? null : (int) $empresa,
        );

        $cache->set($clave, $actor);

        return $actor;
    }

    public function esAdministrador(): bool
    {
        return $this->user->hasRole(Acceso::ADMINISTRADOR);
    }

    public function requiereUsuario(): int
    {
        return $this->usuarioId
            ?? abort(403, 'Su cuenta no está vinculada a un usuario institucional (seguridad.usuario).');
    }

    public function requiereEstudiante(): int
    {
        return $this->estudianteId
            ?? abort(403, 'Su cuenta no está vinculada a un registro de estudiante.');
    }

    public function requiereDocente(): int
    {
        return $this->docenteId
            ?? abort(403, 'Su cuenta no está vinculada a un registro de docente.');
    }

    public function requiereEmpresa(): int
    {
        return $this->empresaId
            ?? abort(403, 'Su cuenta no está vinculada a una empresa activa.');
    }
}
