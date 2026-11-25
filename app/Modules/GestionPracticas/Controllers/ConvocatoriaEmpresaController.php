<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Requests\ConvocatoriaRequest;
use App\Modules\GestionPracticas\Services\ConvocatoriaService;
use App\Modules\GestionPracticas\Support\Actor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** La empresa crea, corrige y envía a revisión sus convocatorias. */
final class ConvocatoriaEmpresaController extends Controller
{
    public function __construct(private readonly ConvocatoriaService $servicio) {}

    public function index(): View
    {
        $convocatorias = Convocatoria::where('id_empresa', Actor::actual()->requiereEmpresa())
            ->withCount(['observaciones as observaciones_pendientes' => fn ($q) => $q->where('estado', 'pendiente')])
            ->orderByDesc('registrada_en')->get();

        return view('modules.gestion-practicas.empresa.convocatorias.index', ['convocatorias' => $convocatorias]);
    }

    public function create(): View
    {
        Actor::actual()->requiereEmpresa();

        return view('modules.gestion-practicas.empresa.convocatorias.form', ['convocatoria' => new Convocatoria]);
    }

    public function store(ConvocatoriaRequest $request): RedirectResponse
    {
        $convocatoria = $this->servicio->crear(Actor::actual(), $request->validated());

        return redirect()->route('gestion-practicas.empresa.convocatorias.show', $convocatoria)
            ->with('status', 'Convocatoria creada como borrador. Envíela a revisión cuando esté lista.');
    }

    public function show(Convocatoria $convocatoria): View
    {
        Gate::authorize('gestionar', $convocatoria);

        return view('modules.gestion-practicas.empresa.convocatorias.show', [
            'convocatoria' => $convocatoria,
            'observaciones' => $convocatoria->observaciones()->orderByDesc('registrado_en')->get(),
            'postulantes' => $convocatoria->postulaciones()->count(),
        ]);
    }

    public function edit(Convocatoria $convocatoria): View
    {
        Gate::authorize('gestionar', $convocatoria);

        return view('modules.gestion-practicas.empresa.convocatorias.form', ['convocatoria' => $convocatoria]);
    }

    public function update(ConvocatoriaRequest $request, Convocatoria $convocatoria): RedirectResponse
    {
        Gate::authorize('gestionar', $convocatoria);

        $this->servicio->actualizar($convocatoria, $request->validated());

        return redirect()->route('gestion-practicas.empresa.convocatorias.show', $convocatoria)
            ->with('status', 'Convocatoria actualizada.');
    }

    public function enviar(Convocatoria $convocatoria): RedirectResponse
    {
        Gate::authorize('gestionar', $convocatoria);

        $this->servicio->enviarARevision($convocatoria);

        return back()->with('status', 'Convocatoria enviada a revisión del administrador.');
    }

    public function cerrar(Convocatoria $convocatoria): RedirectResponse
    {
        Gate::authorize('gestionar', $convocatoria);

        $this->servicio->cerrar($convocatoria);

        return back()->with('status', 'Convocatoria cerrada.');
    }

    public function anular(Convocatoria $convocatoria): RedirectResponse
    {
        Gate::authorize('gestionar', $convocatoria);

        $this->servicio->anular($convocatoria);

        return redirect()->route('gestion-practicas.empresa.convocatorias.index')->with('status', 'Convocatoria anulada.');
    }
}
