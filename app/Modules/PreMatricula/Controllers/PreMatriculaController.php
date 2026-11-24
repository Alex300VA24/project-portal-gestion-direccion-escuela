<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Modules\PreMatricula\Policies\PrematriculaPolicy;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Entrada pública del módulo: presenta el módulo o lleva a cada rol a su espacio. */
final class PreMatriculaController extends ModuleController
{
    protected const MODULE = 'Pre Matrícula';

    protected const SCHEMA = 'prematricula';

    protected const VIEW = 'modules.pre-matricula.index';

    public function entrada(Request $request, PrematriculaPolicy $policy): View|RedirectResponse
    {
        $user = $request->user();

        if ($user !== null && $policy->usarComoAlumno($user)) {
            return redirect()->route('pre-matricula.alumno.index');
        }

        if ($user !== null && $policy->coordinar($user)) {
            return redirect()->route('pre-matricula.coordinacion.index');
        }

        return $this->index();
    }
}
