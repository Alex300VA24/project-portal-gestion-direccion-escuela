<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Evaluacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Requests\EvaluacionRequest;
use App\Modules\GestionPracticas\Requests\SeguimientoRequest;
use App\Modules\GestionPracticas\Services\EvaluacionService;
use App\Modules\GestionPracticas\Services\SeguimientoService;
use App\Modules\GestionPracticas\Support\Actor;
use App\Modules\GestionPracticas\Support\Catalogos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** El docente supervisor ve sus prácticas asignadas, hace seguimiento, evalúa y cierra. */
final class SupervisorController extends Controller
{
    public function __construct(private readonly EvaluacionService $evaluaciones) {}

    public function index(): View
    {
        $practicas = Practica::where('id_docente_supervisor', Actor::actual()->requiereDocente())
            ->orderByRaw("case estado when 'en_curso' then 0 else 1 end")->orderByDesc('fecha_inicio')->get();

        return view('modules.gestion-practicas.supervisor.practicas.index', [
            'practicas' => $practicas,
            'estudiantes' => Catalogos::estudiantes($practicas->pluck('id_estudiante')),
            'empresas' => Catalogos::empresas($practicas->pluck('id_empresa')),
        ]);
    }

    public function show(Practica $practica): View
    {
        Gate::authorize('supervisar', $practica);

        $evaluacion = Evaluacion::where('id_practica', $practica->id_practica)
            ->where('evaluador', Evaluacion::SUPERVISOR)->first();

        return view('modules.gestion-practicas.supervisor.practicas.show', [
            'practica' => $practica,
            'estudiante' => Catalogos::estudiantes([$practica->id_estudiante])[$practica->id_estudiante] ?? '—',
            'empresa' => Catalogos::empresas([$practica->id_empresa])[$practica->id_empresa] ?? '—',
            'documentos' => Catalogos::documentosDe($practica->id_practica),
            'seguimientos' => $practica->seguimientos()->orderByDesc('fecha')->get(),
            'criterios' => $this->evaluaciones->criterios(),
            'evaluacion' => $evaluacion,
            'notasActuales' => $evaluacion === null ? [] : DB::table('gestion_practicas.evaluacion_criterio')
                ->where('id_evaluacion', $evaluacion->id_evaluacion)->pluck('nota', 'id_criterio')->all(),
            'notaFinal' => $evaluacion === null ? null : $this->evaluaciones->notaFinal($practica),
            'tiposSeguimiento' => Catalogos::TIPOS_SEGUIMIENTO,
        ]);
    }

    public function seguimiento(SeguimientoRequest $request, Practica $practica, SeguimientoService $servicio): RedirectResponse
    {
        Gate::authorize('supervisar', $practica);

        $servicio->registrar($practica, Actor::actual(), $request->validated());

        return back()->with('status', 'Seguimiento registrado.');
    }

    public function evaluar(EvaluacionRequest $request, Practica $practica): RedirectResponse
    {
        Gate::authorize('supervisar', $practica);

        $this->evaluaciones->registrar($practica, Actor::actual(), $request->validated('notas'), $request->validated('comentario'));

        return back()->with('status', 'Evaluación registrada.');
    }

    public function cerrar(Practica $practica): RedirectResponse
    {
        Gate::authorize('supervisar', $practica);

        $this->evaluaciones->cerrar($practica, Actor::actual());

        return redirect()->route('gestion-practicas.supervisor.practicas.index')
            ->with('status', 'Práctica cerrada y finalizada.');
    }
}
