<?php

namespace App\Modules\TesisProyectoTesis\Services;

use App\Modules\TesisProyectoTesis\Models\Proyecto;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
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
     * Avisos recientes. Cada uno lleva un token cifrado (no expone el id numérico) para abrirlo.
     *
     * @return list<array{titulo: string, mensaje: string, fecha: Carbon, leida: bool, token: string}>
     */
    public function recientes(int $idUsuario, int $limite = 8): array
    {
        return DB::table('comun.notificacion')
            ->where('id_modulo', $this->catalogo->idModulo())
            ->where('id_usuario_destino', $idUsuario)
            ->orderByDesc('creada_en')
            ->orderByDesc('id_notificacion')
            ->limit($limite)
            ->get(['id_notificacion', 'titulo', 'mensaje', 'creada_en', 'leida_en'])
            ->map(fn ($n) => [
                'titulo' => (string) $n->titulo,
                'mensaje' => (string) $n->mensaje,
                'fecha' => Carbon::parse($n->creada_en),
                'leida' => $n->leida_en !== null,
                'token' => Crypt::encryptString('tesis-notificacion:'.$n->id_notificacion),
            ])
            ->all();
    }

    /**
     * Abre un aviso del usuario: valida el token, comprueba el destinatario y lo marca como leído.
     *
     * @return array{evento: string, id_proyecto: ?int}|null
     */
    public function abrir(string $token, int $idUsuario): ?array
    {
        try {
            $valor = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        if (preg_match('/^tesis-notificacion:(\d+)$/', $valor, $m) !== 1) {
            return null;
        }

        $q = DB::table('comun.notificacion')
            ->where('id_notificacion', (int) $m[1])
            ->where('id_modulo', $this->catalogo->idModulo())
            ->where('id_usuario_destino', $idUsuario);
        $n = $q->first(['tipo_evento', 'referencia_tabla', 'referencia_id', 'leida_en']);

        if ($n === null) {
            return null;
        }
        if ($n->leida_en === null) {
            $q->update(['leida_en' => Carbon::now()]);
        }

        return [
            'evento' => (string) $n->tipo_evento,
            'id_proyecto' => $n->referencia_tabla === 'tesis.proyecto_tesis' ? (int) $n->referencia_id : null,
        ];
    }

    /** Borra los avisos de los proyectos indicados (reinicio de escenarios demo). */
    public function eliminarDeProyectos(array $idsProyecto): void
    {
        if ($idsProyecto !== []) {
            DB::table('comun.notificacion')->where('id_modulo', $this->catalogo->idModulo())
                ->where('referencia_tabla', 'tesis.proyecto_tesis')->whereIn('referencia_id', $idsProyecto)->delete();
        }
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
