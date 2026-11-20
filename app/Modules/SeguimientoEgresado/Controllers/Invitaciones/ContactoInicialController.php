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

/**
 * Pasos 3 y 4 de cada promoción: la Secretaría envía a los alumnos el enlace con el que dejan sus datos de contacto.
 * El enlace se muestra una sola vez y, si el correo está configurado, también se envía al alumno.
 * Se actúa desde la página de la promoción (PromocionController@show); aquí solo se generan los enlaces.
 */
final class ContactoInicialController extends Controller
{
    public function __construct(private readonly ContactoInicialService $contactoInicial) {}

    public function invitar(Egresado $egresado): RedirectResponse
    {
        // Si ya tenía un enlace vigente (perdido, por ejemplo), el nuevo conserva su misma fecha límite.
        $previa = $this->contactoInicial->ultimasInvitaciones([$egresado->id])->get($egresado->id);
        $limite = $previa && $previa->estado === 'PENDIENTE' && Carbon::parse($previa->expira_en)->isFuture()
            ? Carbon::parse($previa->expira_en)
            : null;

        $enlace = $this->contactoInicial->invitar($egresado, $limite);

        return redirect()->to(route('seguimiento-egresado.padron.promocion', array_merge(['promocion' => $egresado->id_cohorte], request()->only('q', 'estado'))).'#paso-4')
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

        return redirect()->to(route('seguimiento-egresado.padron.promocion', ['promocion' => $datos['cohorte']]).'#paso-4')
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
}
