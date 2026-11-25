<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\CasoBienestar;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\Especialista;
use App\Modules\Bienestar\Requests\CasoRequest;
use App\Modules\Bienestar\Services\CasoBienestarService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Modules\Bienestar\Support\FiltrosConsulta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class CasoBienestarController extends Controller
{
    public function __construct(private readonly CasoBienestarService $servicio) {}

    public function index(Request $request): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);
        $filtros = FiltrosConsulta::texto($request);
        $buscar = trim($filtros['buscar'] ?? '');

        $casos = CasoBienestar::query()
            ->with(['estudiante.persona', 'tipoCaso'])
            ->withCount(['citas', 'derivaciones'])
            ->when(($filtros['estado'] ?? '') === 'abierto', fn ($q) => $q->where('estado', '!=', 'Cerrado'))
            ->when(($filtros['estado'] ?? '') === 'cerrado', fn ($q) => $q->where('estado', 'Cerrado'))
            ->when($buscar !== '', fn ($q) => $q->whereHas('estudiante', fn ($e) => $e
                ->where('codigo_universitario', 'like', "%{$buscar}%")
                ->orWhereHas('persona', fn ($p) => $p->where('nombres', 'ilike', "%{$buscar}%")->orWhere('apellido_paterno', 'ilike', "%{$buscar}%"))))
            ->orderByDesc('abierto_en')
            ->paginate(15)->withQueryString();

        return view('modules.bienestar.panel.casos.index', compact('casos', 'filtros'));
    }

    public function show(CasoBienestar $caso): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);
        $caso->load(['estudiante.persona', 'tipoCaso', 'historial', 'citas', 'derivaciones.especialistaDestino', 'alertas']);

        return view('modules.bienestar.panel.casos.show', [
            'caso' => $caso,
            'especialistas' => Especialista::activos()->with('especialidad')->get(),
        ]);
    }

    public function programarCita(CasoRequest $request, CasoBienestar $caso): RedirectResponse
    {
        $this->servicio->programarCita($caso, $request->input('inicio_cita'), $request->input('modalidad'), $this->idUsuario($request));

        return back()->with('status', 'Cita programada. El estudiante recibió una notificación.');
    }

    public function registrarCita(CasoRequest $request, Cita $cita): RedirectResponse
    {
        $this->servicio->cambiarEstadoCita($cita, $request->input('estado'), $request->input('notas'));

        return back()->with('status', 'Resultado de la cita registrado.');
    }

    public function cerrar(CasoRequest $request, CasoBienestar $caso): RedirectResponse
    {
        $this->servicio->cerrar($caso, $request->input('observacion'), $this->idUsuario($request));

        return back()->with('status', "Caso #{$caso->id_caso} cerrado. Sus alertas quedaron como atendidas.");
    }

    private function idUsuario(Request $request): int
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        return $actor->idUsuario;
    }
}
