<?php

namespace App\Modules\SeguimientoEgresado\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Models\PlanCurricular;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\ContactoInicialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Página principal del padrón: cada promoción avanza por cuatro pasos (promoción, alumnos, enlaces, contacto).
 * La lista muestra en qué paso va cada una y la página de la promoción permite avanzar o retomar sin cambiar de pantalla.
 */
final class PromocionController extends Controller
{
    /** Filas máximas que se listan en el seguimiento; el filtro o la búsqueda acotan el resto. */
    private const LIMITE = 100;

    public function __construct(private readonly ContactoInicialService $contactoInicial) {}

    public function index(): View
    {
        return view('modules.seguimiento-egresado.paginas.promociones', [
            'avances' => $this->contactoInicial->avancePorPromocion(),
            'planes' => PlanCurricular::orderBy('vigencia_desde')->get(['id', 'codigo_version', 'estado']),
        ]);
    }

    public function show(Request $request, Cohorte $promocion): View
    {
        $filtros = $request->validate([
            'q' => ['nullable', 'string', 'max:80'],
            'estado' => ['nullable', 'in:todos,pendientes,vencidos,completaron,sin_enlace'],
        ]);
        $estado = $filtros['estado'] ?? 'todos';

        $avance = $this->contactoInicial->avancePorPromocion($promocion->id)->first();

        $alumnos = Egresado::where('id_cohorte', $promocion->id)->with('contactoVigente')
            ->buscar($filtros['q'] ?? null)
            ->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres')->get();
        $invitaciones = $this->contactoInicial->ultimasInvitaciones($alumnos->pluck('id')->all());

        $filas = $alumnos->map(fn (Egresado $alumno) => [
            'alumno' => $alumno,
            'invitacion' => $invitaciones->get($alumno->id),
            'estado' => $this->contactoInicial->estadoDe($alumno, $invitaciones->get($alumno->id)),
        ]);
        $clave = ['pendientes' => 'pendiente', 'vencidos' => 'vencido', 'completaron' => 'completo', 'sin_enlace' => 'sin_enlace'];
        if ($estado !== 'todos') {
            $filas = $filas->where('estado', $clave[$estado]);
        }

        return view('modules.seguimiento-egresado.paginas.promocion', [
            'promocion' => $promocion,
            'avance' => $avance,
            'filas' => $filas->take(self::LIMITE)->values(),
            'totalFilas' => $filas->count(),
            'limite' => self::LIMITE,
            'planes' => PlanCurricular::orderBy('vigencia_desde')->get(['id', 'codigo_version', 'estado']),
            'fechaLimiteSugerida' => now()->addDays(max(1, (int) config('seguimiento-egresado.correo.dias_contacto_inicial')))->toDateString(),
            'filtros' => ['q' => $filtros['q'] ?? '', 'estado' => $estado],
        ]);
    }
}
