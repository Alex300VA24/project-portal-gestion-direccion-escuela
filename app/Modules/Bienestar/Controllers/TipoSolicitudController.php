<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\TipoSolicitud;
use App\Modules\Bienestar\Models\TipoSolicitudRequisito;
use App\Modules\Bienestar\Requests\StoreTipoSolicitudRequest;
use App\Modules\Bienestar\Requests\UpdateTipoSolicitudRequest;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

final class TipoSolicitudController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());

        $tipos = TipoSolicitud::with(['requisitos'])
            ->orderBy('nombre')
            ->paginate(15);

        return view('modules.bienestar.catalogo.index', [
            'tipos' => $tipos,
            'actor' => $actor,
        ]);
    }

    public function create(): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        return redirect()->route('bienestar.catalogo-solicitudes.index', ['abrir' => 'm-tramite']);
    }

    public function store(StoreTipoSolicitudRequest $request): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());

        $tipo = DB::transaction(function () use ($request, $actor) {
            $tipo = TipoSolicitud::create([
                'nombre' => $request->input('nombre'),
                'descripcion' => $request->input('descripcion'),
                'unidad_responsable' => $request->input('unidad_responsable'),
                'plazo_dias_habiles' => $request->integer('plazo_dias_habiles'),
                'base_normativa_fuente' => $request->input('base_normativa_fuente'),
                'estado_verificacion' => $request->input('estado_verificacion', 'pendiente_verificar'),
                'activo' => $request->boolean('activo', true),
            ]);

            // Crear requisitos asociados si se adjuntaron
            $requisitos = $request->input('requisitos', []);
            $archivosPlantilla = $request->file('requisitos', []);

            $orden = 0;
            foreach ($requisitos as $index => $reqData) {
                if (empty($reqData['nombre'])) {
                    continue;
                }

                $idPlantilla = null;
                if (! empty($archivosPlantilla[$index]['plantilla'])) {
                    $file = $archivosPlantilla[$index]['plantilla'];
                    $uuid = (string) Str::uuid();
                    $ext = $file->getClientOriginalExtension() ?: 'bin';
                    $ruta = "bienestar/plantillas/{$uuid}.{$ext}";
                    Storage::disk('local')->putFileAs('bienestar/plantillas', $file, "{$uuid}.{$ext}");

                    $idPlantilla = DB::table('comun.archivo')->insertGetId([
                        'id_modulo' => DB::table('seguridad.modulo_sistema')->where('codigo', 'bienestar')->value('id_modulo') ?? 2,
                        'nombre_original' => $file->getClientOriginalName(),
                        'ruta_almacenamiento' => $ruta,
                        'tipo_mime' => $file->getClientMimeType() ?: 'application/octet-stream',
                        'tamano_bytes' => $file->getSize() ?: 0,
                        'hash_sha256' => hash_file('sha256', $file->getRealPath()),
                        'subido_por' => $actor->idUsuario,
                        'subido_en' => now(),
                    ], 'id_archivo');
                }

                TipoSolicitudRequisito::create([
                    'id_tipo_solicitud' => $tipo->id_tipo_solicitud,
                    'nombre' => $reqData['nombre'],
                    'descripcion' => $reqData['descripcion'] ?? 'Documento sustentatorio',
                    'es_obligatorio' => ! empty($reqData['es_obligatorio']),
                    'formatos_permitidos' => $reqData['formatos_permitidos'] ?? 'pdf,jpg,png',
                    'tamano_maximo_mb' => (int) ($reqData['tamano_maximo_mb'] ?? 5),
                    'id_archivo_plantilla' => $idPlantilla,
                    'orden' => ++$orden,
                    'activo' => true,
                ]);
            }

            return $tipo;
        });

        return redirect()
            ->route('bienestar.catalogo-solicitudes.edit', $tipo)
            ->with('status', 'Trámite registrado. Agrega o revisa sus requisitos.');
    }

    public function edit(Request $request, TipoSolicitud $tipoSolicitud): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $actor = ActorBienestarResolver::resolve($request->user());
        $tipoSolicitud->load('requisitos');

        return view('modules.bienestar.catalogo.form', [
            'tipo' => $tipoSolicitud,
            'actor' => $actor,
        ]);
    }

    public function update(UpdateTipoSolicitudRequest $request, TipoSolicitud $tipoSolicitud): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $tipoSolicitud->update([
            'nombre' => $request->input('nombre'),
            'descripcion' => $request->input('descripcion'),
            'unidad_responsable' => $request->input('unidad_responsable'),
            'plazo_dias_habiles' => $request->integer('plazo_dias_habiles'),
            'base_normativa_fuente' => $request->input('base_normativa_fuente'),
            'estado_verificacion' => $request->input('estado_verificacion'),
            'activo' => $request->boolean('activo', true),
        ]);

        return redirect()
            ->route('bienestar.catalogo-solicitudes.index')
            ->with('status', 'Tipo de solicitud actualizado en el catálogo UNT.');
    }

    public function toggleActivo(Request $request, TipoSolicitud $tipoSolicitud): RedirectResponse
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        $tipoSolicitud->update([
            'activo' => ! $tipoSolicitud->activo,
        ]);

        $estadoTxt = $tipoSolicitud->activo ? 'activado' : 'desactivado';

        return back()->with('status', "El tipo de solicitud ha sido {$estadoTxt} correctamente.");
    }
}
