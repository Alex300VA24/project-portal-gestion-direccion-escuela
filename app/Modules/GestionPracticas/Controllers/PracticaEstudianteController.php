<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Models\Postulacion;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Requests\PracticaRequest;
use App\Modules\GestionPracticas\Services\DocumentoService;
use App\Modules\GestionPracticas\Services\PracticaService;
use App\Modules\GestionPracticas\Support\Actor;
use App\Modules\GestionPracticas\Support\Catalogos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** El estudiante seleccionado registra su práctica, adjunta documentos y la envía a validación. */
final class PracticaEstudianteController extends Controller
{
    public function __construct(private readonly PracticaService $servicio) {}

    public function index(): View
    {
        $practicas = Practica::where('id_estudiante', Actor::actual()->requiereEstudiante())
            ->orderByDesc('registrada_en')->get();

        return view('modules.gestion-practicas.estudiante.practicas.index', [
            'practicas' => $practicas,
            'empresas' => Catalogos::empresas($practicas->pluck('id_empresa')),
        ]);
    }

    public function create(Postulacion $postulacion): View|RedirectResponse
    {
        Gate::authorize('gestionar', $postulacion);

        try {
            $this->servicio->exigirSinPracticaActiva(Actor::actual()->requiereEstudiante(), 'postulacion', 'registrar otra práctica');
        } catch (ValidationException $e) {
            return redirect()->route('gestion-practicas.estudiante.postulaciones.index')->withErrors($e->errors());
        }

        return view('modules.gestion-practicas.estudiante.practicas.form', [
            'practica' => new Practica,
            'postulacion' => $postulacion,
            'convocatoria' => Convocatoria::find($postulacion->id_convocatoria),
            'periodos' => Catalogos::periodos(),
        ]);
    }

    public function store(PracticaRequest $request, Postulacion $postulacion): RedirectResponse
    {
        Gate::authorize('gestionar', $postulacion);

        $practica = $this->servicio->registrar(
            Actor::actual(), $postulacion, $request->safe()->except('carta'), $request->file('carta'),
        );

        return redirect()->route('gestion-practicas.estudiante.practicas.show', $practica)
            ->with('status', 'Práctica registrada. Revise los datos y envíela a validación.');
    }

    public function show(Practica $practica): View
    {
        Gate::authorize('view', $practica);

        return view('modules.gestion-practicas.estudiante.practicas.show', [
            'practica' => $practica,
            'empresa' => Catalogos::empresas([$practica->id_empresa])[$practica->id_empresa] ?? '—',
            'supervisor' => Catalogos::docentes([$practica->id_docente_supervisor])[$practica->id_docente_supervisor] ?? null,
            'documentos' => Catalogos::documentosDe($practica->id_practica),
            'observaciones' => $practica->observaciones()->orderByDesc('registrado_en')->get(),
            'seguimientos' => $practica->seguimientos()->orderByDesc('fecha')->get(),
            'tiposDocumento' => Catalogos::TIPOS_DOCUMENTO,
        ]);
    }

    public function edit(Practica $practica): View
    {
        Gate::authorize('gestionar', $practica);

        return view('modules.gestion-practicas.estudiante.practicas.form', [
            'practica' => $practica,
            'postulacion' => null,
            'convocatoria' => null,
            'periodos' => Catalogos::periodos(),
        ]);
    }

    public function update(PracticaRequest $request, Practica $practica, DocumentoService $documentos): RedirectResponse
    {
        Gate::authorize('gestionar', $practica);

        $this->servicio->actualizar($practica, $request->safe()->except('carta'));

        if ($request->hasFile('carta')) {
            $documentos->subir($practica, Actor::actual(), $request->file('carta'), 'carta_aceptacion');
        }

        return redirect()->route('gestion-practicas.estudiante.practicas.show', $practica)
            ->with('status', 'Datos de la práctica actualizados.');
    }

    public function enviar(Practica $practica): RedirectResponse
    {
        Gate::authorize('gestionar', $practica);

        $this->servicio->enviarARevision($practica);

        return back()->with('status', 'Información enviada a revisión del administrador.');
    }
}
