<?php

namespace App\Modules\GestionPracticas\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\GestionPracticas\Models\Convocatoria;
use App\Modules\GestionPracticas\Requests\ObservacionRequest;
use App\Modules\GestionPracticas\Services\ConvocatoriaService;
use App\Modules\GestionPracticas\Support\Actor;
use App\Modules\GestionPracticas\Support\Catalogos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** El administrador revisa las convocatorias: las aprueba y publica, o las observa. */
final class RevisionConvocatoriaController extends Controller
{
    public function __construct(private readonly ConvocatoriaService $servicio) {}

    public function index(Request $request): View
    {
        $estado = $request->query('estado', Convocatoria::EN_REVISION);

        $convocatorias = Convocatoria::query()
            ->when($estado !== 'todas', fn ($q) => $q->where('estado', $estado))
            ->orderByDesc('enviada_en')->orderByDesc('registrada_en')->limit(200)->get();

        return view('modules.gestion-practicas.revision.convocatorias.index', [
            'convocatorias' => $convocatorias,
            'empresas' => Catalogos::empresas($convocatorias->pluck('id_empresa')),
            'estado' => $estado,
        ]);
    }

    public function show(Convocatoria $convocatoria): View
    {
        return view('modules.gestion-practicas.revision.convocatorias.show', [
            'convocatoria' => $convocatoria,
            'empresa' => Catalogos::empresas([$convocatoria->id_empresa])[$convocatoria->id_empresa] ?? '—',
            'observaciones' => $convocatoria->observaciones()->orderByDesc('registrado_en')->get(),
        ]);
    }

    public function aprobar(Convocatoria $convocatoria): RedirectResponse
    {
        $this->servicio->aprobar($convocatoria, Actor::actual());

        return redirect()->route('gestion-practicas.revision.convocatorias.index')
            ->with('status', 'Convocatoria aprobada y publicada.');
    }

    public function observar(ObservacionRequest $request, Convocatoria $convocatoria): RedirectResponse
    {
        $this->servicio->observar($convocatoria, Actor::actual(), $request->validated('descripcion'));

        return redirect()->route('gestion-practicas.revision.convocatorias.index')
            ->with('status', 'Observación registrada: la empresa debe corregir la convocatoria.');
    }
}
