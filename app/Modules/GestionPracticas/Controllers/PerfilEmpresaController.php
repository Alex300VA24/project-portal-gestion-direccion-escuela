<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Requests\EmpresaPerfilRequest;
use App\Modules\GestionPracticas\Services\EmpresaService;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** La empresa completa o actualiza su perfil. */
final class PerfilEmpresaController extends Controller
{
    public function edit(): View
    {
        $empresa = DB::table('comun.empresa')->where('id_empresa', Actor::actual()->requiereEmpresa())->first();

        return view('modules.gestion-practicas.empresa.perfil', ['empresa' => $empresa]);
    }

    public function update(EmpresaPerfilRequest $request, EmpresaService $empresas): RedirectResponse
    {
        $empresas->actualizarPerfil(Actor::actual()->requiereEmpresa(), $request->validated());

        return back()->with('status', 'Perfil de la empresa actualizado.');
    }
}
