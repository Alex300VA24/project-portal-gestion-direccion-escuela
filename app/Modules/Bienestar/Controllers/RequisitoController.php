<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Models\TipoSolicitudRequisito;
use App\Modules\Bienestar\Requests\RequisitoRequest;
use App\Modules\Bienestar\Services\SolicitudTramiteService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class RequisitoController extends Controller
{
    public function __construct(private readonly SolicitudTramiteService $tramite) {}

    public function store(RequisitoRequest $request, TipoSolicitud $tipoSolicitud): RedirectResponse
    {
        TipoSolicitudRequisito::create($this->datos($request) + [
            'id_tipo_solicitud' => $tipoSolicitud->id_tipo_solicitud,
            'orden' => (int) $tipoSolicitud->requisitos()->max('orden') + 1,
            'activo' => true,
        ]);

        return back()->with('status', 'Requisito agregado al trámite.');
    }

    public function update(RequisitoRequest $request, TipoSolicitudRequisito $requisito): RedirectResponse
    {
        $requisito->update($this->datos($request));

        return back()->with('status', 'Requisito actualizado.');
    }

    public function toggle(TipoSolicitudRequisito $requisito): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);
        $requisito->update(['activo' => ! $requisito->activo]);

        return back()->with('status', $requisito->activo ? 'Requisito activado.' : 'Requisito desactivado.');
    }

    /** Plantilla descargable: la ven Bienestar, Secretaría y los estudiantes si el requisito está vigente. */
    public function plantilla(Request $request, TipoSolicitudRequisito $requisito): StreamedResponse
    {
        $usuario = $request->user();
        $gestiona = BienestarPermissions::check($usuario, BienestarPermissions::GESTIONAR);
        abort_unless($gestiona || BienestarPermissions::check($usuario, BienestarPermissions::SOLICITAR)
            || BienestarPermissions::check($usuario, BienestarPermissions::SECRETARIA), 403);

        $archivo = $requisito->plantilla;
        abort_unless($archivo && ($gestiona || $requisito->activo)
            && Storage::disk('local')->exists($archivo->ruta_almacenamiento), 404);

        return Storage::disk('local')->download($archivo->ruta_almacenamiento, basename($archivo->nombre_original));
    }

    private function datos(RequisitoRequest $request): array
    {
        $datos = [
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'es_obligatorio' => $request->boolean('es_obligatorio'),
            'formatos_permitidos' => implode(',', $request->input('formatos')),
            'tamano_maximo_mb' => $request->integer('tamano_maximo_mb'),
        ];
        if ($request->hasFile('plantilla')) {
            $actor = ActorBienestarResolver::resolve($request->user());
            ActorBienestarResolver::ensureUsuario($actor);
            $datos['id_archivo_plantilla'] = $this->tramite->guardarArchivoPrivado($request->file('plantilla'), $actor->idUsuario, 'plantillas');
        } elseif ($request->boolean('quitar_plantilla')) {
            $datos['id_archivo_plantilla'] = null;
        }

        return $datos;
    }
}
