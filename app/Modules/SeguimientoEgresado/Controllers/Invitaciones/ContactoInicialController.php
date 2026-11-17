<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Invitaciones;

use App\Http\Controllers\Controller;
use App\Modules\SeguimientoEgresado\Models\Cohorte;
use App\Modules\SeguimientoEgresado\Models\Egresado;
use App\Modules\SeguimientoEgresado\Services\Invitaciones\ContactoInicialService;
use App\Modules\SeguimientoEgresado\Support\Esquema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Contacto inicial desde el padrón: la Secretaría genera el enlace con el que el egresado deja sus datos de contacto.
 * El enlace se muestra una sola vez y, si el correo está configurado, también se envía al egresado.
 */
final class ContactoInicialController extends Controller
{
    /** Filas máximas que se listan; el filtro por cohorte o búsqueda acota el resto. */
    private const LIMITE = 100;

    public function __construct(private readonly ContactoInicialService $contactoInicial) {}

    public function index(Request $request): View
    {
        $filtros = $request->validate([
            'cohorte' => ['nullable', 'integer', 'exists:'.Esquema::validar('cohorte').',id'],
            'q' => ['nullable', 'string', 'max:80'],
            'estado' => ['nullable', 'in:todos,sin_contacto,con_contacto'],
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
        }

        $total = (clone $consulta)->count();
        $egresados = $consulta->orderBy('apellido_paterno')->orderBy('apellido_materno')->orderBy('nombres')->limit(self::LIMITE)->get();

        return view('modules.seguimiento-egresado.paginas.contacto-inicial', [
            'egresados' => $egresados,
            'total' => $total,
            'limite' => self::LIMITE,
            'invitaciones' => $this->contactoInicial->ultimasInvitaciones($egresados->pluck('id')->all()),
            'cohortes' => Cohorte::orderByDesc('anio_ingreso')->orderBy('codigo_promocion')->get(),
            'filtros' => ['cohorte' => $filtros['cohorte'] ?? '', 'q' => $filtros['q'] ?? '', 'estado' => $estado],
        ]);
    }

    public function invitar(Egresado $egresado): RedirectResponse
    {
        $enlace = $this->contactoInicial->invitar($egresado);

        return redirect()->route('seguimiento-egresado.padron.contacto-inicial', request()->only('cohorte', 'q', 'estado'))
            ->with('enlaces_contacto', [$enlace])
            ->with('resumen_contacto', $this->resumen([$enlace]));
    }

    public function invitarCohorte(Request $request): RedirectResponse
    {
        $datos = $request->validate(['cohorte' => ['required', 'integer', 'exists:'.Esquema::validar('cohorte').',id']], [
            'cohorte.required' => 'Elige la cohorte a la que se invitará.',
            'cohorte.exists' => 'La cohorte elegida no existe.',
        ]);

        $resultado = $this->contactoInicial->invitarCohorte(Cohorte::findOrFail($datos['cohorte']));

        $resumen = $this->resumen($resultado['enlaces']);
        if ($resultado['con_contacto'] > 0) {
            $resumen .= " Se omitieron {$resultado['con_contacto']} que ya dejaron su contacto.";
        }
        if ($resultado['con_enlace'] > 0) {
            $resumen .= " Se omitieron {$resultado['con_enlace']} que ya tienen un enlace pendiente (usa «Enlace nuevo» si lo perdieron).";
        }

        return redirect()->route('seguimiento-egresado.padron.contacto-inicial', ['cohorte' => $datos['cohorte']])
            ->with('enlaces_contacto', $resultado['enlaces'])
            ->with('resumen_contacto', $resumen);
    }

    /** Dice la verdad sobre el correo: cuántos salieron de verdad y cuántos quedan para entregar a mano. */
    private function resumen(array $enlaces): string
    {
        $total = count($enlaces);
        if ($total === 0) {
            return 'No había egresados por invitar.';
        }

        $enviados = count(array_filter($enlaces, fn (array $e) => $e['correo'] === 'enviado'));
        $texto = $total === 1 ? 'Se generó 1 enlace personal.' : "Se generaron {$total} enlaces personales.";
        $texto .= $enviados === 0
            ? ' Ninguno se envió por correo: entrégalos con los enlaces de abajo.'
            : ($enviados === $total ? ' Todos se enviaron por correo.' : " {$enviados} se enviaron por correo; para el resto usa los enlaces de abajo.");

        return $texto;
    }
}
