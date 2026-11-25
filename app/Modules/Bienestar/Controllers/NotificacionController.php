<?php

namespace App\Modules\Bienestar\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Bienestar\Models\Notificacion;
use App\Modules\Bienestar\Services\NotificacionService;
use App\Modules\Bienestar\Support\ActorBienestarResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

final class NotificacionController extends Controller
{
    public function __construct(private readonly NotificacionService $servicio) {}

    public function index(Request $request): View
    {
        return view('modules.bienestar.notificaciones.index', [
            'notificaciones' => $this->servicio->propias($this->idUsuario($request))->paginate(20),
        ]);
    }

    public function abrir(Request $request, Notificacion $notificacion): RedirectResponse
    {
        abort_unless($notificacion->id_usuario_destino === $this->idUsuario($request), 404);
        $notificacion->leida_en ?? $notificacion->update(['leida_en' => now()]);

        return redirect()->to($this->servicio->destino($notificacion));
    }

    /** Avisos sin leer para mostrarlos al momento, sin recargar la página. */
    public function recientes(Request $request, NotificacionService $servicio): JsonResponse
    {
        $id = ActorBienestarResolver::resolve($request->user())->idUsuario;

        return response()->json([
            'sinLeer' => $id ? $servicio->sinLeer($id) : 0,
            'avisos' => $id ? $servicio->propias($id)->whereNull('leida_en')->limit(3)->get()->map(fn ($n) => [
                'id' => $n->id_notificacion, 'titulo' => $n->titulo, 'mensaje' => $n->mensaje, 'url' => route('bienestar.notificaciones.abrir', $n),
            ]) : [],
        ])->header('Cache-Control', 'no-store');
    }

    public function leerTodas(Request $request): RedirectResponse
    {
        $this->servicio->propias($this->idUsuario($request))->whereNull('leida_en')->reorder()->update(['leida_en' => now()]);

        return back()->with('status', 'Todas tus notificaciones quedaron como leídas.');
    }

    private function idUsuario(Request $request): int
    {
        $actor = ActorBienestarResolver::resolve($request->user());
        ActorBienestarResolver::ensureUsuario($actor);

        return $actor->idUsuario;
    }
}
