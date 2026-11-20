<?php

namespace App\Modules\SeguimientoEgresado\Controllers\Egresado;

use App\Modules\SeguimientoEgresado\Policies\ExpedientePolicy;
use App\Modules\SeguimientoEgresado\Requests\ContactoRequest;
use App\Modules\SeguimientoEgresado\Services\Egresado\ContactoService;
use App\Modules\SeguimientoEgresado\Services\Egresado\HistorialService;
use App\Modules\SeguimientoEgresado\Services\Egresado\ResultadoContacto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class ContactoController extends EgresadoController
{
    public function __construct(private readonly HistorialService $historial) {}

    public function editar(Request $request): View
    {
        $identidad = $this->identidad($request);
        $versiones = $this->historial->contactos($identidad->egresadoId);

        return view('modules.seguimiento-egresado.egresado.contacto', [
            'contacto' => collect($versiones)->firstWhere('vigente_hasta', null),
            'versiones' => $versiones,
        ]);
    }

    public function guardar(ContactoRequest $request, ContactoService $contactos): RedirectResponse
    {
        $identidad = $this->identidad($request);
        abort_unless((new ExpedientePolicy)->modificar($identidad, $identidad->egresadoId), 403);

        // El servicio versiona el contacto y, si el enlace era de contacto inicial, completa esa invitación.
        $resultado = $contactos->guardar($identidad->egresadoId, $request->validated(), 'FORMULARIO', $identidad->invitacionId);

        return redirect()->route('seguimiento-egresado.egresado.inicio')->with('exito', match ($resultado) {
            ResultadoContacto::Creado => 'Gracias, registramos tus datos de contacto.',
            ResultadoContacto::Actualizado => 'Actualizamos tus datos de contacto. Conservamos los anteriores en tu historial.',
            ResultadoContacto::SinCambios => 'Tus datos de contacto ya estaban al día.',
        });
    }
}
