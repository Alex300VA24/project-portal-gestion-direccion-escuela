<?php

namespace App\Modules\PreMatricula\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\PreMatricula\Requests\ActualizarCuentaRequest;
use App\Modules\PreMatricula\Requests\CoordinacionRequest;
use App\Modules\PreMatricula\Requests\CrearCuentaRequest;
use App\Modules\PreMatricula\Services\CuentasEstudiantes;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Cuentas de estudiantes: listar, crear y cambiar correo, contraseña o estado, como en master. */
final class CuentaController extends Controller
{
    use RespondeAcciones;

    public function __construct(private readonly CuentasEstudiantes $cuentas) {}

    public function index(CoordinacionRequest $request): View
    {
        return view('modules.pre-matricula.coordinacion.cuentas', [
            'buscar' => (string) $request->validated('buscar'),
            'cuentas' => $this->cuentas->listar($request->validated('buscar')),
        ]);
    }

    public function store(CrearCuentaRequest $request): RedirectResponse
    {
        return $this->accion(fn () => $this->cuentas->crear($request->validated()), 'Cuenta creada. El estudiante ya puede iniciar sesión.', 'pre-matricula.coordinacion.cuentas');
    }

    public function edit(CoordinacionRequest $request, int $estudiante): View
    {
        return view('modules.pre-matricula.coordinacion.cuenta', ['cuenta' => $this->cuentas->cuenta($estudiante)]);
    }

    public function update(ActualizarCuentaRequest $request, int $estudiante): RedirectResponse
    {
        return $this->accion(fn () => $this->cuentas->actualizar($estudiante, $request->datos()), 'Cuenta actualizada.', 'pre-matricula.coordinacion.cuentas');
    }
}
