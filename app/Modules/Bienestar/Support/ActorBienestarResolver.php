<?php

namespace App\Modules\Bienestar\Support;

use App\Models\User;
use App\Modules\Bienestar\Exceptions\FaltanDatosDemoException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ActorBienestarResolver
{
    public static function resolve(?User $user = null): ActorContext
    {
        /** @var User|null $currentUser */
        $currentUser = $user ?? Auth::user();

        if (! $currentUser) {
            return new ActorContext(
                idUsuario: null,
                idPersona: null,
                idEstudiante: null,
                codigoEstudiante: null,
                idDocente: null,
                codigoDocente: null,
                role: null,
                nombreCompleto: 'Invitado',
                email: '',
            );
        }

        $email = (string) $currentUser->email;
        $roleName = $currentUser->roles()->first()?->name;

        // 1. Obtener usuario de seguridad.usuario
        $segUsuario = DB::table('seguridad.usuario')
            ->where('correo_institucional', $email)
            ->first();

        $idUsuario = $segUsuario ? (int) $segUsuario->id_usuario : null;
        $idPersona = $segUsuario ? (int) $segUsuario->id_persona : null;

        $idEstudiante = null;
        $codigoEstudiante = null;
        $idDocente = null;
        $codigoDocente = null;

        if ($idPersona) {
            // 2. Resolver estudiante si aplica
            $estudiante = DB::table('core.estudiante')
                ->where('id_persona', $idPersona)
                ->first();

            if ($estudiante) {
                $idEstudiante = (int) $estudiante->id_estudiante;
                $codigoEstudiante = (string) $estudiante->codigo_universitario;
            }

            // 3. Resolver docente si aplica
            $docente = DB::table('core.docente')
                ->where('id_persona', $idPersona)
                ->first();

            if ($docente) {
                $idDocente = (int) $docente->id_docente;
                $codigoDocente = (string) $docente->codigo_docente;
            }
        }

        return new ActorContext(
            idUsuario: $idUsuario,
            idPersona: $idPersona,
            idEstudiante: $idEstudiante,
            codigoEstudiante: $codigoEstudiante,
            idDocente: $idDocente,
            codigoDocente: $codigoDocente,
            role: $roleName,
            nombreCompleto: (string) ($currentUser->name ?? 'Usuario'),
            email: $email,
        );
    }

    public static function ensureUsuario(ActorContext $actor): void
    {
        if ($actor->idUsuario === null) {
            throw new FaltanDatosDemoException('No se encontró el registro del usuario en seguridad.usuario');
        }
    }

    public static function ensureEstudiante(ActorContext $actor): void
    {
        self::ensureUsuario($actor);

        if ($actor->idEstudiante === null) {
            throw new FaltanDatosDemoException('No se encontró el perfil de estudiante en core.estudiante');
        }
    }

    public static function ensureDocente(ActorContext $actor): void
    {
        self::ensureUsuario($actor);

        if ($actor->idDocente === null) {
            throw new FaltanDatosDemoException('No se encontró el perfil de docente en core.docente');
        }
    }
}
