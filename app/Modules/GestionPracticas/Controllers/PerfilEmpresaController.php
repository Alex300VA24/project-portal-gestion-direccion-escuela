<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Requests\EmpresaPasswordRequest;
use App\Modules\GestionPracticas\Requests\EmpresaPerfilRequest;
use App\Modules\GestionPracticas\Services\EmpresaService;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** La empresa completa o actualiza su perfil y cambia la contraseña de su cuenta. */
final class PerfilEmpresaController extends Controller
{
    public function edit(EmpresaService $empresas): View
    {
        $actor = Actor::actual();
        $empresa = DB::table('comun.empresa')->where('id_empresa', $actor->requiereEmpresa())->first();

        return view('modules.gestion-practicas.empresa.perfil', [
            'empresa' => $empresa,
            'debeCambiarPassword' => $empresas->debeCambiarPassword($actor->user),
        ]);
    }

    public function update(EmpresaPerfilRequest $request, EmpresaService $empresas): RedirectResponse
    {
        $empresas->actualizarPerfil(Actor::actual()->requiereEmpresa(), $request->validated());

        return back()->with('status', 'Perfil de la empresa actualizado.');
    }

    public function password(EmpresaPasswordRequest $request, EmpresaService $empresas): RedirectResponse
    {
        $empresas->cambiarPassword(Actor::actual()->user, $request->validated('password'));

        return back()->with('status', 'Contraseña actualizada. Úsela la próxima vez que inicie sesión.');
    }
}
