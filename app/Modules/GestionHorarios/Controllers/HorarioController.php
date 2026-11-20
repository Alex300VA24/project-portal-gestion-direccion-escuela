<?php

namespace App\Modules\GestionHorarios\Controllers;

use App\Modules\GestionHorarios\Repositories\HorariosPublicadosRepository;
use App\Modules\GestionHorarios\Requests\HorarioRequest;
use App\Shared\Controllers\ModuleController;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

final class HorarioController extends ModuleController
{
    protected const MODULE = 'Gestión de Horarios';

    protected const SCHEMA = 'gestion_horarios';

    protected const VIEW = 'modules.gestion-horarios.index';

    public function publicados(): JsonResponse
    {
        return response()->json(['data' => $this->consultaPublicados()->get()]);
    }

    public function consultarPublicados(Request $request): View|Response
    {
        $filtros = $request->validate(['periodo' => ['nullable', 'integer', 'min:1']]);
        try {
            $repo = new HorariosPublicadosRepository(DB::connection());
            $periodos = $repo->periodos();
            $periodo = isset($filtros['periodo']) ? (int) $filtros['periodo'] : null;
            $catalogos = $periodo && $periodos->contains(fn ($p) => (int) $p->id_periodo === $periodo)
                ? $repo->catalogos($periodo) : ['ciclos' => collect(), 'secciones' => collect(), 'ambientes' => collect(), 'docentes' => collect()];
            $consulta = $this->consultaPublicados();
            if (isset($filtros['periodo'])) {
                $consulta->where('id_periodo', $filtros['periodo']);
            }

            return view('modules.gestion-horarios.publicados-pagina', [
                'horarios' => $consulta->paginate(20)->withQueryString(),
                'periodo' => $filtros['periodo'] ?? '',
                'periodos' => $periodos,
                'catalogos' => $catalogos,
            ]);
        } catch (QueryException $e) {
            report($e);

            return response()->view('modules.gestion-horarios.publicados-error', [], 503);
        }
    }

    public function semanaPublicada(Request $request): View|Response
    {
        $filtros = $request->validate([
            'periodo' => ['required', 'integer', 'min:1'],
            'modalidad' => ['required', 'in:ciclo,ambiente,docente'],
            'valor' => ['required', 'integer', 'min:1'],
            'seccion' => ['nullable', 'string', 'max:20', 'regex:/^[\pL\pN -]+$/u'],
        ]);
        try {
            $repo = new HorariosPublicadosRepository(DB::connection());
            $periodo = $repo->periodos()->first(fn ($p) => (int) $p->id_periodo === (int) $filtros['periodo']);
            if (! $periodo) {
                throw ValidationException::withMessages(['periodo' => 'Seleccione un periodo con horarios publicados.']);
            }
            $catalogos = $repo->catalogos((int) $filtros['periodo']);
            $valor = (int) $filtros['valor'];
            $permitido = match ($filtros['modalidad']) {
                'ciclo' => $catalogos['ciclos']->contains(fn ($v) => (int) $v === $valor),
                'ambiente' => $catalogos['ambientes']->contains('id', $valor),
                'docente' => $catalogos['docentes']->contains('id', $valor),
            };
            if (! $permitido) {
                throw ValidationException::withMessages(['valor' => 'La opción seleccionada no corresponde al periodo.']);
            }
            if (! empty($filtros['seccion']) && ($filtros['modalidad'] !== 'ciclo' || ! $catalogos['secciones']->contains(fn ($s) => $s['ciclo'] === $valor && $s['codigo'] === $filtros['seccion']))) {
                throw ValidationException::withMessages(['seccion' => 'La sección no corresponde al ciclo y periodo seleccionados.']);
            }
            $sesiones = $repo->sesiones(
                (int) $filtros['periodo'], $filtros['modalidad'], (int) $filtros['valor'], $filtros['seccion'] ?? null
            );
            $descripcion = match ($filtros['modalidad']) {
                'ciclo' => 'Ciclo '.$valor,
                'ambiente' => ($catalogos['ambientes']->firstWhere('id', $valor)['codigo'] ?? '').' · '.($catalogos['ambientes']->firstWhere('id', $valor)['nombre'] ?? ''),
                'docente' => $catalogos['docentes']->firstWhere('id', $valor)['nombre'] ?? '',
            };

            return view('modules.gestion-horarios.semana-publicada', compact('filtros', 'sesiones', 'periodo', 'descripcion'));
        } catch (QueryException $e) {
            report($e);

            return response()->view('modules.gestion-horarios.publicados-error', [], 503);
        }
    }

    private function consultaPublicados(): Builder
    {
        return DB::table('gestion_horarios.horario')
            ->where('estado', 'PUBLICADO')
            ->whereNotNull('publicado_en')
            ->select('id_horario', 'id_periodo', 'ambito', 'version', 'publicado_en')
            ->orderBy('id_horario');
    }

    public function create(): View
    {
        abort(403, 'La creación de horarios aún no está habilitada.');
    }

    public function store(HorarioRequest $request): RedirectResponse
    {
        abort(403, 'La creación de horarios aún no está habilitada.');
    }
}
