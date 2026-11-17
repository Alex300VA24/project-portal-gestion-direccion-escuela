<?php

namespace App\Modules\Bienestar\Controllers;

use App\Modules\Bienestar\Models\ArticuloInformativo;
use App\Modules\Bienestar\Requests\BienestarRequest;
use App\Modules\Bienestar\Requests\StoreArticuloRequest;
use App\Modules\Bienestar\Services\BienestarService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use App\Modules\Bienestar\Support\BienestarPermissions;
use App\Shared\Controllers\ModuleController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

final class BienestarController extends ModuleController
{
    protected const MODULE = 'Bienestar';

    protected const SCHEMA = 'bienestar';

    protected const VIEW = 'modules.bienestar.index';

    public function store(BienestarRequest $request, BienestarService $service): RedirectResponse
    {
        $service->handle($request->validated());

        return redirect()
            ->route('bienestar.index')
            ->with('status', 'Registro procesado.');
    }

    /**
     * Sección informativa pública: muestra artículos normativos publicados.
     * Si el usuario autenticado tiene permiso de gestión, también puede ver borradores.
     */
    public function informacion(Request $request): View
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        $puedeGestionar = $request->user()?->can(BienestarPermissions::GESTIONAR) ?? false;

        $query = ArticuloInformativo::query()->orderBy('fecha_ultima_revision', 'desc');

        if (! $puedeGestionar) {
            $query->publicados();
        }

        $articulos = $query->paginate(10);

        return view('modules.bienestar.informacion.index', [
            'articulos' => $articulos,
            'puedeGestionar' => $puedeGestionar,
            'actor' => $actor,
        ]);
    }

    public function showArticulo(Request $request, ArticuloInformativo $articulo): View
    {
        $puedeGestionar = $request->user()?->can(BienestarPermissions::GESTIONAR) ?? false;

        if ($articulo->estado === 'borrador' && ! $puedeGestionar) {
            abort(404, 'Artículo no disponible.');
        }

        return view('modules.bienestar.informacion.show', [
            'articulo' => $articulo,
            'puedeGestionar' => $puedeGestionar,
        ]);
    }

    public function createArticulo(): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        return view('modules.bienestar.informacion.form', [
            'articulo' => new ArticuloInformativo,
            'isEditing' => false,
        ]);
    }

    public function storeArticulo(StoreArticuloRequest $request): RedirectResponse
    {
        $actor = ActorBienestarResolver::resolve($request->user());

        ArticuloInformativo::create([
            ...$request->validated(),
            'autor_id' => $actor->idUsuario,
        ]);

        return redirect()
            ->route('bienestar.informacion.index')
            ->with('status', 'Artículo informativo guardado correctamente.');
    }

    public function editArticulo(ArticuloInformativo $articulo): View
    {
        Gate::authorize(BienestarPermissions::GESTIONAR);

        return view('modules.bienestar.informacion.form', [
            'articulo' => $articulo,
            'isEditing' => true,
        ]);
    }

    public function updateArticulo(StoreArticuloRequest $request, ArticuloInformativo $articulo): RedirectResponse
    {
        $articulo->update($request->validated());

        return redirect()
            ->route('bienestar.informacion.show', $articulo)
            ->with('status', 'Artículo informativo actualizado.');
    }
}
