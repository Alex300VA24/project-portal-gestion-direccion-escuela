<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Services\ConvocatoriaService;
use App\Modules\GestionPracticas\Services\PostulacionService;
use App\Modules\GestionPracticas\Support\Actor;
use App\Modules\GestionPracticas\Support\Catalogos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** El estudiante consulta las convocatorias publicadas, revisa sus detalles y postula. */
final class ConvocatoriaEstudianteController extends Controller
{
    public function __construct(
        private readonly ConvocatoriaService $convocatorias,
        private readonly PostulacionService $postulaciones,
    ) {}

    public function index(): View
    {
        $convocatorias = $this->convocatorias->vigentes()->orderBy('fecha_cierre')->get();
        $idEstudiante = Actor::actual()->estudianteId;

        return view('modules.gestion-practicas.estudiante.convocatorias.index', [
            'convocatorias' => $convocatorias,
            'empresas' => Catalogos::empresas($convocatorias->pluck('id_empresa')),
            'yaPostulo' => $idEstudiante === null ? [] : Postulacion::where('id_estudiante', $idEstudiante)
                ->pluck('id_convocatoria')->map(fn ($id) => (int) $id)->all(),
        ]);
    }

    public function show(Convocatoria $convocatoria): View
    {
        Gate::authorize('view', $convocatoria);

        $idEstudiante = Actor::actual()->estudianteId;

        return view('modules.gestion-practicas.estudiante.convocatorias.show', [
            'convocatoria' => $convocatoria,
            'empresa' => Catalogos::empresas([$convocatoria->id_empresa])[$convocatoria->id_empresa] ?? '—',
            'vigente' => $this->convocatorias->estaVigente($convocatoria),
            'yaPostulo' => $idEstudiante !== null && Postulacion::where('id_estudiante', $idEstudiante)
                ->where('id_convocatoria', $convocatoria->id_convocatoria)->exists(),
        ]);
    }

    public function postular(Convocatoria $convocatoria): RedirectResponse
    {
        Gate::authorize('view', $convocatoria);

        $this->postulaciones->postular(Actor::actual(), $convocatoria);

        return redirect()->route('gestion-practicas.estudiante.postulaciones.index')
            ->with('status', 'Postulación registrada. Cuando conozca el resultado, infórmelo aquí.');
    }
}
