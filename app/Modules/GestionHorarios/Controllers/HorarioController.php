<?php

namespace App\Modules\GestionHorarios\Controllers;

use App\Modules\GestionHorarios\Repositories\HorariosPublicadosRepository;
use App\Modules\GestionHorarios\Requests\HorarioRequest;
use App\Modules\GestionHorarios\Services\AmbitoConsultaAdministrativa;
use App\Modules\GestionHorarios\Services\ConsultaPublicadaPresentacion;
use App\Modules\GestionHorarios\Services\DescargaConsultaPublicada;
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
use Illuminate\Support\Facades\Auth;
use App\Models\User;

final class HorarioController extends ModuleController
{
    protected const MODULE = 'Gestión de Horarios';

    protected const SCHEMA = 'gestion_horarios';

    protected const VIEW = 'modules.gestion-horarios.index';

    public function index(): View
    {
        /** @var User|null $usuario */
        $usuario = Auth::user();
        $puedeAdministrar = $usuario
            && $usuario->hasAnyRole(['director_escuela', 'secretaria'])
            && (new AmbitoConsultaAdministrativa)->escuelas($usuario) !== [];
        $revisionesDisponibles = false;

        if ($puedeAdministrar) {
            try {
                $revisionesDisponibles = DB::connection()
                    ->getSchemaBuilder()
                    ->hasTable('gestion_horarios.revision_oferta_pendiente');
            } catch (QueryException $e) {
                report($e);
            }
        }

        return parent::index()->with([
            'puedeAdministrar' => $puedeAdministrar,
            'revisionesDisponibles' => $revisionesDisponibles,
        ]);
    }

    public function publicados(Request $request): JsonResponse|RedirectResponse
    {
        // Conservar el contrato JSON para clientes que lo solicitan explícitamente.
        if (!$request->wantsJson()) {
            return redirect()->route('gestion-horarios.consulta-publicados', $request->only(['periodo','modalidad','valor','seccion']));
        }
        return response()->json(['data' => $this->consultaPublicados()->get()]);
    }

    public function catalogoPublico(): View|Response
    {
        try {
            $horarios = $this->consultaPublicados()->reorder('publicado_en', 'desc')->orderByDesc('id_horario')->paginate(20);

            return view('modules.gestion-horarios.catalogo-publico', compact('horarios'));
        } catch (QueryException $e) {
            report($e);

            return response()->view('modules.gestion-horarios.catalogo-publico', ['horarios' => null], 503);
        }
    }

    public function consultarPublicados(Request $request): View|Response
    {
        if (ConsultaPublicacionDemoController::habilitada($request)) return (new ConsultaPublicacionDemoController)->consulta($request);
        if ($request->filled('valor')) {
            return $this->semanaPublicada($request);
        }
        $filtros = $request->validate(['periodo' => ['nullable', 'integer', 'min:1'], 'modalidad'=>['nullable','in:ciclo,docente,ambiente'], 'descargar'=>['nullable','in:pdf']]);
        abort_if(isset($filtros['descargar']),422,'Seleccione un horario publicado.');
        try {
            $repo = new HorariosPublicadosRepository(DB::connection());
            $periodos = $repo->periodos();
            $periodo = isset($filtros['periodo']) ? (int) $filtros['periodo'] : ($periodos->isNotEmpty() ? (int) $periodos->first()->id_periodo : null);
            $catalogos = $periodo && $periodos->contains(fn ($p) => (int) $p->id_periodo === $periodo)
                ? $repo->catalogos($periodo) : ['ciclos' => collect(), 'secciones' => collect(), 'ambientes' => collect(), 'docentes' => collect()];
            $consulta = $this->consultaPublicados();
            if ($periodo) {
                $consulta->where('id_periodo', $periodo);
            }

            return view('modules.gestion-horarios.publicados-pagina', [
                'horarios' => $consulta->paginate(20)->withQueryString(),
                'periodo' => $periodo ?? '',
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
        if (ConsultaPublicacionDemoController::habilitada($request)) return (new ConsultaPublicacionDemoController)->consulta($request);
        $filtros = $request->validate([
            'periodo' => ['required', 'integer', 'min:1'],
            'modalidad' => ['required', 'in:ciclo,ambiente,docente'],
            'valor' => ['required', 'integer', 'min:1'],
            'seccion' => ['nullable', 'string', 'max:30', 'regex:/^[\pL\pN -]+$/u'],
            'descargar'=>['nullable','in:pdf'],
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

            $consulta = $this->consultaPublicados()->where('id_periodo', $periodo->id_periodo);
            $planos = ConsultaPublicadaPresentacion::cuadriculas($sesiones->all(), $filtros['modalidad'], $descripcion);
            if (($filtros['descargar']??null)==='pdf') return DescargaConsultaPublicada::pdf($planos,$periodo);
            return view('modules.gestion-horarios.publicados-pagina', [
                'filtrosConsulta'=>$filtros,
                'horarios' => $consulta->paginate(20)->withQueryString(), 'periodo' => $periodo->id_periodo,
                'periodos' => $repo->periodos(), 'catalogos' => $catalogos,
                'planos' => $planos, 'descripcion' => $descripcion,
            ]);
        } catch (QueryException $e) {
            report($e);

            return response()->view('modules.gestion-horarios.publicados-error', [], 503);
        }
    }

    public function panel(Request $request): View|Response
    {
        if (ConsultaPublicacionDemoController::habilitada($request)) return (new ConsultaPublicacionDemoController)->panel($request);
        $f = $request->validate(['periodo' => ['nullable','integer','min:1'], 'fragmento' => ['nullable','in:1']]);
        try {
            $repo = new HorariosPublicadosRepository(DB::connection());
            $ahora = \Illuminate\Support\Carbon::now('America/Lima');
            $periodos = $repo->periodosConVigencia();
            $vigente = fn ($p) => ConsultaPublicadaPresentacion::vigente($p, $ahora->format('Y-m-d'));
            $periodo = isset($f['periodo']) ? $periodos->firstWhere('id_periodo', (int)$f['periodo']) : ($periodos->first($vigente) ?? $periodos->first());
            if (isset($f['periodo']) && !$periodo) throw ValidationException::withMessages(['periodo'=>'Seleccione un periodo publicado.']);
            $enVigencia = $periodo && $vigente($periodo);
            $dia = ['DOMINGO','LUNES','MARTES','MIERCOLES','JUEVES','VIERNES','SABADO'][$ahora->dayOfWeek];
            $ambientes = $periodo ? $repo->catalogos((int)$periodo->id_periodo)['ambientes']->all() : [];
            $sesiones = $enVigencia ? $repo->sesionesDeAmbientes((int)$periodo->id_periodo, $dia)->all() : [];
            $tarjetas = ConsultaPublicadaPresentacion::ocupacion($ambientes, $sesiones, $ahora->hour*60+$ahora->minute, (bool)$enVigencia);
            $vista = isset($f['fragmento']) ? 'panel-ocupacion-contenido' : 'panel-ocupacion';
            return response()->view('modules.gestion-horarios.'.$vista, compact('periodos','periodo','ahora','enVigencia','tarjetas'))->header('Cache-Control','no-store, private');
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
