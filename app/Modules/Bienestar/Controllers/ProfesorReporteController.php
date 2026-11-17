<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Contracts\FuenteAcademicaInterface;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Modules\Bienestar\Requests\ReporteDocenteRequest;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

final class ProfesorReporteController extends Controller
{
    public function __construct(
        private readonly FuenteAcademicaInterface $fuenteAcademica,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize(BienestarPermissions::REPORTAR);

        $actor = ActorBienestarResolver::resolve($request->user());

        $reportes = AlertaRiesgo::query()
            ->docentes()
            ->where('id_docente', $actor->idDocente)
            ->with(['estudiante.persona'])
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('modules.bienestar.profesor.index', [
            'reportes' => $reportes,
            'actor' => $actor,
        ]);
    }

    public function create(Request $request): View
    {
        Gate::authorize(BienestarPermissions::REPORTAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        $codigoDocente = $actor->codigoDocente ?? 'DOC-PRF-001';

        $estudiantesAsignados = $this->fuenteAcademica->obtenerEstudiantesDocente($codigoDocente, '2026-I');

        return view('modules.bienestar.profesor.create', [
            'actor' => $actor,
            'estudiantesAsignados' => $estudiantesAsignados,
        ]);
    }

    public function buscarEstudiante(Request $request): JsonResponse
    {
        Gate::authorize(BienestarPermissions::REPORTAR);

        $codigo = trim((string) $request->input('codigo'));
        $key = 'docente-busqueda-estudiante:'.$request->user()?->id;

        if (RateLimiter::tooManyAttempts($key, 10)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'error' => "Demasiados intentos de búsqueda. Por favor intente en {$seconds} segundos.",
            ], 429);
        }

        RateLimiter::hit($key, 60);

        Log::info("Búsqueda de estudiante por docente: usuario {$request->user()?->email} buscó código [{$codigo}]");

        $estudiante = $this->fuenteAcademica->buscarEstudiantePorCodigo($codigo);

        if (! $estudiante) {
            return response()->json(['error' => 'Estudiante no encontrado.'], 404);
        }

        return response()->json($estudiante);
    }

    public function store(ReporteDocenteRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());

        $estudiante = Estudiante::where('codigo_universitario', $request->codigo_estudiante)->firstOrFail();

        AlertaRiesgo::create([
            'id_estudiante' => $estudiante->id_estudiante,
            'periodo' => '2026-I',
            'curso' => $request->curso,
            'regla' => 'reporte_docente',
            'origen' => 'reporte_docente',
            'prioridad' => $request->prioridad,
            'estado' => 'pendiente',
            'id_docente' => $actor->idDocente,
            'motivo_reporte' => $request->motivo_reporte,
            'descripcion_reporte' => $request->descripcion_reporte,
            'fecha_deteccion' => now(),
        ]);

        return redirect()
            ->route('bienestar.profesor.index')
            ->with('status', 'Reporte de riesgo estudiantil enviado correctamente a la Dirección de Bienestar.');
    }
}
