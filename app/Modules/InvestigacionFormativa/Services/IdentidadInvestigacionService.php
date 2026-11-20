<?php

namespace App\Modules\InvestigacionFormativa\Services;

use App\Models\User;
use App\Modules\InvestigacionFormativa\Exceptions\AccesoDenegado;
use App\Modules\InvestigacionFormativa\Support\ActorInvestigacion;
use Illuminate\Support\Facades\DB;

final class IdentidadInvestigacionService
{
    public const ESTADO_ACTIVO = 'Activo';

    public function resolver(User $user): ActorInvestigacion
    {
        $identidad = DB::table('investigacion_formativa.identidad_usuario as i')
            ->join('seguridad.usuario as u', 'u.id_usuario', '=', 'i.id_usuario_seguridad')
            ->where('i.user_id', $user->id)
            ->select([
                'i.activo as mapping_activo',
                'i.id_usuario_seguridad',
                'u.id_persona',
                'u.estado as estado_academico',
            ])
            ->first();

        if ($identidad === null || ! (bool) $identidad->mapping_activo) {
            throw new AccesoDenegado(
                'No existe una vinculación académica activa para este usuario.',
            );
        }

        if ($identidad->estado_academico !== self::ESTADO_ACTIVO) {
            throw new AccesoDenegado(
                'La cuenta académica vinculada no está activa.',
            );
        }

        if ($identidad->id_persona === null) {
            throw new AccesoDenegado(
                'La cuenta académica vinculada no tiene una persona asociada.',
            );
        }

        $idDocente = DB::table('core.docente')
            ->where('id_persona', $identidad->id_persona)
            ->where('estado', self::ESTADO_ACTIVO)
            ->value('id_docente');

        return new ActorInvestigacion(
            user: $user,
            idUsuarioSeguridad: (int) $identidad->id_usuario_seguridad,
            idPersona: (int) $identidad->id_persona,
            idDocente: $idDocente === null ? null : (int) $idDocente,
            roles: $user->getRoleNames()->all(),
        );
    }
}
