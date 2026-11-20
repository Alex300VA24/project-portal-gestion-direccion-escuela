<?php

namespace App\Modules\SeguimientoEstudiante\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class SeguimientoEstudianteService
{
    public function personaVinculada(User $user): int
    {
        $persona = DB::table('seguimiento_estudiante.identidad_cuenta')
            ->where('id_usuario', $user->getKey())
            ->value('id_persona');

        abort_if($persona === null, 403, 'Tu cuenta aún no tiene una identidad académica vinculada.');

        return (int) $persona;
    }
}
