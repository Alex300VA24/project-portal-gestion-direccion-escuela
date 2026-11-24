<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Practica;
use App\Modules\GestionPracticas\Requests\ObservacionRequest;
use App\Modules\GestionPracticas\Requests\ValidarPracticaRequest;
use App\Modules\GestionPracticas\Services\PracticaService;
use App\Modules\GestionPracticas\Support\Actor;
use App\Modules\GestionPracticas\Support\Catalogos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** El administrador revisa la información de la práctica: la valida (y asigna supervisor) o la observa. */
final class RevisionPracticaController extends Controller
{
    public function __construct(private readonly PracticaService $servicio) {}

    public function index(Request $request): View
    {
        $estado = $request->query('estado', Practica::EN_REVISION);

        $practicas = Practica::query()
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('enviada_en')->orderByDesc('registrada_en')->limit(200)->get();

        return view('modules.gestion-practicas.revision.practicas.index', [
            'practicas' => $practicas,
            'estudiantes' => Catalogos::estudiantes($practicas->pluck('id_estudiante')),
            'empresas' => Catalogos::empresas($practicas->pluck('id_empresa')),
            'estado' => $estado,
        ]);
    }

    public function show(Practica $practica): View
    {
        return view('modules.gestion-practicas.revision.practicas.show', [
            'practica' => $practica,
            'estudiante' => Catalogos::estudiantes([$practica->id_estudiante])[$practica->id_estudiante] ?? '—',
            'empresa' => Catalogos::empresas([$practica->id_empresa])[$practica->id_empresa] ?? '—',
            'supervisor' => Catalogos::docentes([$practica->id_docente_supervisor])[$practica->id_docente_supervisor] ?? null,
            'documentos' => Catalogos::documentosDe($practica->id_practica),
            'observaciones' => $practica->observaciones()->orderByDesc('registrado_en')->get(),
            'docentes' => Catalogos::docentesActivos(),
        ]);
    }

    public function validar(ValidarPracticaRequest $request, Practica $practica): RedirectResponse
    {
        $this->servicio->validar($practica, Actor::actual(), (int) $request->validated('id_docente'));

        return redirect()->route('gestion-practicas.revision.practicas.index')
            ->with('status', 'Práctica validada, supervisor asignado y estado «en curso».');
    }

    public function observar(ObservacionRequest $request, Practica $practica): RedirectResponse
    {
        $idDocumento = $request->validated('id_documento');

        $this->servicio->observar(
            $practica, Actor::actual(), $request->validated('descripcion'), $idDocumento === null ? null : (int) $idDocumento,
        );

        return redirect()->route('gestion-practicas.revision.practicas.index')
            ->with('status', 'Observación registrada: el estudiante debe corregir la información.');
    }
}
