<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\RegistroEstudianteRequest;
use App\Modules\PreMatricula\Services\CuentasEstudiantes;
use App\Modules\PreMatricula\Services\PrematriculaException;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Registro público del estudiante, como en master: la contraseña inicial es su código universitario. */
final class RegistroEstudianteController extends Controller
{
    public function create(): View
    {
        return view('modules.pre-matricula.registro');
    }

    public function store(RegistroEstudianteRequest $request, CuentasEstudiantes $cuentas): RedirectResponse
    {
        $datos = $request->validated();

        try {
            $cuentas->crear([...$datos, 'password' => $datos['codigo_universitario']]);
        } catch (PrematriculaException $error) {
            return back()->withInput()->with('error', $error->getMessage());
        }

        return redirect()->route('login')
            ->with('status', 'Registro completado correctamente. Ya puedes iniciar sesión con tu correo institucional; tu contraseña es tu código universitario.');
    }
}
