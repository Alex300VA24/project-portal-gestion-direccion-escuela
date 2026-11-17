<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Invitaciones;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\ContactoInicialService;
use App\Modules\SeguimientoEgresado\Support\Esquema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Contacto inicial desde el padrón: la Secretaría genera el enlace con el que el alumno deja sus datos de contacto.
 * El enlace se muestra una sola vez y, si el correo está configurado, también se envía al alumno.
 * Dejar el contacto es obligatorio para poder confirmar su egreso.
 */
final class ContactoInicialController extends Controller
{
    /** Filas máximas que se listan; el filtro por promoción o búsqueda acota el resto. */
    private const LIMITE = 100;

    public function __construct(private readonly ContactoInicialService $contactoInicial) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'cohorte' => ['nullable', 'integer', 'exists:'.Esquema::validar('cohorte').',id'],
            'q' => ['nullable', 'string', 'max:80'],
            'estado' => ['nullable', 'in:todos,sin_contacto,vencido,con_contacto'],
        ]);
        $estado = $filtros['estado'] ?? 'sin_contacto';

        $consulta = Egresado::query()
            ->with(['cohorte', 'contactoVigente'])
            ->buscar($filtros['q'] ?? null)
            ->porCohorte($filtros['cohorte'] ?? null);

        if ($estado === 'sin_contacto') {
            $consulta->whereDoesntHave('contactoVigente');
        } elseif ($estado === 'con_contacto') {
            $consulta->whereHas('contactoVigente');
        } elseif ($estado === 'vencido') {
            // Con enlace de contacto inicial que ya pasó su fecha límite y sin contacto registrado.
            $consulta->whereDoesntHave('contactoVigente')->whereHas('invitaciones', fn ($q) => $q
                ->where('proposito', 'CONTACTO_INICIAL')->whereIn('estado', ['PENDIENTE', 'EXPIRADA'])->where('expira_en', '<', now()));
        }

        $total = (clone $consulta)->count();
        $egresados = $consulta->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres')->limit(self::LIMITE)->get();

        return view('modules.seguimiento-egresado.paginas.contacto-inicial', [
            'egresados' => $egresados,
            'total' => $total,
            'limite' => self::LIMITE,
            'invitaciones' => $this->contactoInicial->ultimasInvitaciones($egresados->pluck('id')->all()),
            'cohortes' => Cohorte::orderByDesc('anio_ingreso')->orderBy('codigo_promocion')->get(),
            'seguimiento' => $this->seguimientoPorPromocion(),
            'fechaLimiteSugerida' => now()->addDays(max(1, (int) config('seguimiento-egresado.correo.dias_contacto_inicial')))->toDateString(),
            'filtros' => ['cohorte' => $filtros['cohorte'] ?? '', 'q' => $filtros['q'] ?? '', 'estado' => $estado],
        ]);
    }

    public function invitar(Egresado $egresado): RedirectResponse
    {
        // Si ya tenía un enlace vigente (perdido, por ejemplo), el nuevo conserva su misma fecha límite.
        $previa = $this->contactoInicial->ultimasInvitaciones([$egresado->id])->get($egresado->id);
        $limite = $previa && $previa->estado === 'PENDIENTE' && Carbon::parse($previa->expira_en)->isFuture()
            ? Carbon::parse($previa->expira_en)
            : null;

        $enlace = $this->contactoInicial->invitar($egresado, $limite);

        return redirect()->route('seguimiento-egresado.padron.contacto-inicial', request()->only('cohorte', 'q', 'estado'))
            ->with('enlaces_contacto', [$enlace])
            ->with('success', $this->resumen([$enlace]));
    }

    public function invitarCohorte(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'cohorte' => ['required', 'integer', 'exists:'.Esquema::validar('cohorte').',id'],
            'fecha_limite' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.now()->addYear()->toDateString()],
        ], [
            'cohorte.required' => 'Elige la promoción a la que se enviará el contacto inicial.',
            'cohorte.exists' => 'La promoción elegida no existe.',
            'fecha_limite.required' => 'Indica hasta qué fecha pueden completar su contacto.',
            'fecha_limite.date' => 'La fecha límite no es válida.',
            'fecha_limite.after_or_equal' => 'La fecha límite no puede ser anterior a hoy.',
            'fecha_limite.before_or_equal' => 'La fecha límite no puede pasar de un año.',
        ]);

        $limite = Carbon::parse($datos['fecha_limite']);
        $resultado = $this->contactoInicial->invitarCohorte(Cohorte::findOrFail($datos['cohorte']), $limite);

        $resumen = $this->resumen($resultado['enlaces']);
        if ($resultado['enlaces'] !== []) {
            $resumen .= ' Tienen hasta el '.$limite->format('d/m/Y').' para completar su contacto.';
        }
        if ($resultado['con_contacto'] > 0) {
            $resumen .= " Se omitieron {$resultado['con_contacto']} que ya dejaron su contacto.";
        }
        if ($resultado['con_enlace'] > 0) {
            $resumen .= " Se omitieron {$resultado['con_enlace']} que ya tienen un enlace pendiente (usa «Enlace nuevo» si lo perdieron).";
        }

        return redirect()->route('seguimiento-egresado.padron.contacto-inicial', ['cohorte' => $datos['cohorte']])
            ->with('enlaces_contacto', $resultado['enlaces'])
            ->with('success', $resumen);
    }

    /** Dice la verdad sobre el correo: cuántos salieron de verdad y cuántos quedan para entregar a mano. */
    private function resumen(array $enlaces): string
    {
        $total = count($enlaces);
        if ($total === 0) {
            return 'No había alumnos por invitar.';
        }

        $enviados = count(array_filter($enlaces, fn (array $e) => $e['correo'] === 'enviado'));
        $texto = $total === 1 ? 'Se generó 1 enlace personal.' : "Se generaron {$total} enlaces personales.";
        $texto .= $enviados === 0
            ? ' Entrega a cada egresado su enlace de abajo.'
            : ($enviados === $total ? ' Todos se enviaron por correo.' : " {$enviados} se enviaron por correo; para el resto usa los enlaces de abajo.");

        return $texto;
    }

    /**
     * Por cada promoción: cuántos alumnos ya dejaron su contacto, cuántos siguen pendientes dentro de plazo, cuántos
     * dejaron vencer su enlace sin completarlo y cuántos aún no tienen enlace. Es lo que mira la Secretaría al llegar la fecha límite.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function seguimientoPorPromocion(): Collection
    {
        $egresados = Egresado::with('contactoVigente')->get(['id', 'id_cohorte']);
        $invitaciones = $this->contactoInicial->ultimasInvitaciones($egresados->pluck('id')->all());
        $promociones = Cohorte::all()->keyBy('id');

        return $egresados->groupBy('id_cohorte')->map(function (Collection $grupo, int $idPromocion) use ($invitaciones, $promociones) {
            $fila = ['promocion' => $promociones->get($idPromocion), 'total' => $grupo->count(), 'completaron' => 0, 'pendientes' => 0, 'vencidos' => 0, 'sin_enlace' => 0, 'fecha_limite' => null];

            foreach ($grupo as $egresado) {
                if ($egresado->contactoVigente !== null) {
                    $fila['completaron']++;

                    continue;
                }

                $invitacion = $invitaciones->get($egresado->id);
                if ($invitacion === null || $invitacion->estado === 'ANULADA') {
                    $fila['sin_enlace']++;

                    continue;
                }

                $vence = Carbon::parse($invitacion->expira_en);
                $fila[$invitacion->estado === 'PENDIENTE' && $vence->isFuture() ? 'pendientes' : 'vencidos']++;
                if ($fila['fecha_limite'] === null || $vence->greaterThan($fila['fecha_limite'])) {
                    $fila['fecha_limite'] = $vence;
                }
            }

            return $fila;
        })->filter(fn (array $fila) => $fila['promocion'] !== null)
            ->sortByDesc(fn (array $fila) => $fila['promocion']->anio_ingreso)
            ->values();
    }
}
