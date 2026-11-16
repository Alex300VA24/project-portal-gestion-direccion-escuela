<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Proyecto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Avisos del módulo en comun.notificacion.
 */
final class NotificacionService
{
    public function __construct(private readonly CatalogoService $catalogo) {}

    /**
     * @param  list<int|null>  $idsUsuario
     */
    public function notificar(array $idsUsuario, string $evento, string $titulo, string $mensaje, ?Proyecto $proyecto = null): void
    {
        $destinos = array_values(array_unique(array_filter($idsUsuario, fn ($v) => $v !== null)));

        if ($destinos === []) {
            return;
        }

        $ahora = Carbon::now();
        DB::table('comun.notificacion')->insert(array_map(fn (int $id) => [
            'id_modulo' => $this->catalogo->idModulo(),
            'id_usuario_destino' => $id,
            'tipo_evento' => mb_substr($evento, 0, 80),
            'titulo' => mb_substr($titulo, 0, 180),
            'mensaje' => $mensaje,
            'referencia_tabla' => $proyecto ? 'tesis.proyecto_tesis' : null,
            'referencia_id' => $proyecto?->id_proyecto,
            'canal' => 'sistema',
            'creada_en' => $ahora,
            'enviada_en' => $ahora,
        ], $destinos));
    }

    public function yaNotificado(Proyecto $proyecto, string $evento): bool
    {
        return DB::table('comun.notificacion')
            ->where('id_modulo', $this->catalogo->idModulo())
            ->where('referencia_tabla', 'tesis.proyecto_tesis')
            ->where('referencia_id', $proyecto->id_proyecto)
            ->where('tipo_evento', $evento)
            ->exists();
    }

    /**
     * @return list<array{titulo: string, mensaje: string, fecha: Carbon, leida: bool}>
     */
    public function recientes(int $idUsuario, int $limite = 8): array
    {
        return DB::table('comun.notificacion')
            ->where('id_modulo', $this->catalogo->idModulo())
            ->where('id_usuario_destino', $idUsuario)
            ->orderByDesc('creada_en')
            ->orderByDesc('id_notificacion')
            ->limit($limite)
            ->get(['titulo', 'mensaje', 'creada_en', 'leida_en'])
            ->map(fn ($n) => [
                'titulo' => (string) $n->titulo,
                'mensaje' => (string) $n->mensaje,
                'fecha' => Carbon::parse($n->creada_en),
                'leida' => $n->leida_en !== null,
            ])
            ->all();
    }

    public function noLeidas(int $idUsuario): int
    {
        return DB::table('comun.notificacion')
            ->where('id_modulo', $this->catalogo->idModulo())
            ->where('id_usuario_destino', $idUsuario)
            ->whereNull('leida_en')
            ->count();
    }

    public function marcarLeidas(int $idUsuario): void
    {
        DB::table('comun.notificacion')
            ->where('id_modulo', $this->catalogo->idModulo())
            ->where('id_usuario_destino', $idUsuario)
            ->whereNull('leida_en')
            ->update(['leida_en' => Carbon::now()]);
    }
}
