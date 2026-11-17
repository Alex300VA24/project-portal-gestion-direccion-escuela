<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\Beneficio;
use App\Modules\Bienestar\Models\Cita;
use App\Modules\Bienestar\Models\SolicitudApoyo;
use App\Modules\Bienestar\Requests\StoreSolicitudApoyoRequest;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class AlumnoBienestarController extends Controller
{
    public function solicitudes(Request $request): View
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        if (! $actor->idEstudiante) {
            abort(403, 'Su cuenta no tiene un perfil de estudiante vinculado.');
        }

        $solicitudes = SolicitudApoyo::query()
            ->where('id_estudiante', $actor->idEstudiante)
            ->with(['beneficio'])
            ->orderBy('solicitada_en', 'desc')
            ->paginate(10);

        return view('modules.bienestar.alumno.solicitudes.index', [
            'solicitudes' => $solicitudes,
            'actor' => $actor,
        ]);
    }

    public function crearSolicitud(): View
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);

        $beneficios = Beneficio::where('activo', true)->get();

        return view('modules.bienestar.alumno.solicitudes.create', [
            'beneficios' => $beneficios,
        ]);
    }

    public function guardarSolicitud(StoreSolicitudApoyoRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        if (! $actor->idEstudiante) {
            abort(403, 'Su cuenta no tiene un perfil de estudiante vinculado.');
        }

        SolicitudApoyo::create([
            'id_estudiante' => $actor->idEstudiante,
            'id_beneficio' => $request->integer('id_beneficio'),
            'motivo' => $request->input('motivo'),
            'estado' => 'Pendiente',
            'solicitada_en' => now(),
        ]);

        return redirect()
            ->route('bienestar.alumno.solicitudes.index')
            ->with('status', 'Su solicitud de apoyo socioeconómico ha sido enviada con éxito para evaluación.');
    }

    public function citas(Request $request): View
    {
        Gate::authorize(BienestarPermissions::SOLICITAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        if (! $actor->idEstudiante) {
            abort(403, 'Su cuenta no tiene un perfil de estudiante vinculado.');
        }

        $citas = Cita::query()
            ->whereHas('caso', function ($q) use ($actor) {
                $q->where('id_estudiante', $actor->idEstudiante);
            })
            ->with(['caso.tipoCaso'])
            ->orderBy('inicio', 'desc')
            ->paginate(10);

        return view('modules.bienestar.alumno.citas.index', [
            'citas' => $citas,
            'actor' => $actor,
        ]);
    }
}
