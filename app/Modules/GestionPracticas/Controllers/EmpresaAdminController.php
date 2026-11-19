<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Requests\EmpresaNuevaRequest;
use App\Modules\GestionPracticas\Services\EmpresaService;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/** El administrador gestiona las empresas y sus cuentas de acceso. */
final class EmpresaAdminController extends Controller
{
    public function index(): View
    {
        $empresas = DB::table('comun.empresa as e')
            ->leftJoin('gestion_practicas.empresa_usuario as eu', function ($join): void {
                $join->on('eu.id_empresa', '=', 'e.id_empresa')->where('eu.es_principal', true);
            })
            ->leftJoin('seguridad.usuario as u', 'u.id_usuario', '=', 'eu.id_usuario')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('gestion_practicas.empresa_usuario as x')->whereColumn('x.id_empresa', 'e.id_empresa'))
            ->orderBy('e.razon_social')
            ->get(['e.id_empresa', 'e.ruc', 'e.razon_social', 'e.nombre_comercial', 'e.estado', 'u.correo_institucional as cuenta']);

        return view('modules.gestion-practicas.admin.empresas.index', ['empresas' => $empresas]);
    }

    public function create(): View
    {
        return view('modules.gestion-practicas.admin.empresas.create');
    }

    public function store(EmpresaNuevaRequest $request, EmpresaService $empresas): RedirectResponse
    {
        $empresas->crear(Actor::actual(), $request->validated());

        return redirect()->route('gestion-practicas.empresas.index')
            ->with('status', 'Empresa registrada y cuenta de acceso creada.');
    }

    public function cambiarEstado(int $empresa, EmpresaService $empresas): RedirectResponse
    {
        abort_unless(DB::table('gestion_practicas.empresa_usuario')->where('id_empresa', $empresa)->exists(), 404);

        $activa = ! $empresas->estaActiva($empresa);
        $empresas->cambiarEstado($empresa, $activa);

        return back()->with('status', $activa ? 'Empresa activada.' : 'Empresa desactivada.');
    }
}
