<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Requests\PostulacionResultadoRequest;
use App\Modules\GestionPracticas\Services\PostulacionService;
use App\Modules\GestionPracticas\Support\Actor;
use App\Modules\GestionPracticas\Support\Catalogos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Postulaciones del estudiante y el resultado que informa («¿fue seleccionado?»). */
final class PostulacionController extends Controller
{
    public function index(): View
    {
        $postulaciones = Postulacion::where('id_estudiante', Actor::actual()->requiereEstudiante())
            ->orderByDesc('postulado_en')->get();

        $convocatorias = Convocatoria::whereIn('id_convocatoria', $postulaciones->pluck('id_convocatoria'))
            ->get()->keyBy('id_convocatoria');

        $activa = Practica::activaDe(Actor::actual()->estudianteId);

        return view('modules.gestion-practicas.estudiante.postulaciones.index', [
            'practicaActiva' => $activa,
            'empresaActiva' => $activa === null ? null : (Catalogos::empresas([$activa->id_empresa])[$activa->id_empresa] ?? null),
            'postulaciones' => $postulaciones,
            'convocatorias' => $convocatorias,
            'empresas' => Catalogos::empresas($convocatorias->pluck('id_empresa')),
        ]);
    }

    public function resultado(PostulacionResultadoRequest $request, Postulacion $postulacion, PostulacionService $servicio): RedirectResponse
    {
        Gate::authorize('gestionar', $postulacion);

        $servicio->registrarResultado($postulacion, Actor::actual(), $request->validated('resultado'));

        return $request->validated('resultado') === Postulacion::SELECCIONADO
            ? redirect()->route('gestion-practicas.estudiante.practicas.create', $postulacion)
                ->with('status', 'Felicitaciones. Registre ahora los datos de su práctica.')
            : back()->with('status', 'Resultado registrado.');
    }
}
