<?php

namespace App\Modules\SeguimientoEstudiante\Services;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class SeguimientoEstudianteService
{
    public function personaVinculada(User $user): int
    {
        $persona = DB::table('seguimiento_estudiante.identidad_cuenta')
            ->where('id_usuario', $user->getKey())
            ->value('id_persona');

        throw_if($persona === null, AuthorizationException::class, 'Tu cuenta aún no tiene una identidad académica vinculada.');

        return (int) $persona;
    }
}
