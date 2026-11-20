<?php

namespace App\Modules\SeguimientoEstudiante\Services\Tutoria;

use App\Modules\SeguimientoEstudiante\Policies\ExpedientePolicy;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class AvisoTutoriaService
{
    private const TABLE = 'seguimiento_estudiante.tutoria_aviso';

    public function __construct(private readonly ExpedientePolicy $policy) {}

    public function avisar(int $persona, string $mensaje): void
    {
        $cuenta = DB::table('seguimiento_estudiante.identidad_cuenta')
            ->where('id_persona', $persona)->sharedLock()->value('id_usuario');
        if ($cuenta !== null) {
            DB::table(self::TABLE)->insert(['id_usuario' => $cuenta, 'id_persona' => $persona, 'mensaje' => $mensaje]);
        }
    }

    public function avisarTutores(int $estudiante, int $periodo, string $mensaje): void
    {
        $personas = $this->policy->tutoriasVigentes()
            ->where('asignacion.id_estudiante', $estudiante)
            ->where('asignacion.id_periodo', $periodo)
            ->select('docente.id_persona')->distinct()->pluck('id_persona');

        foreach ($personas as $persona) {
            $this->avisar($persona, $mensaje);
        }
    }

    public function bandeja(int $cuenta, int $persona): Builder
    {
        return DB::table(self::TABLE)->where('id_usuario', $cuenta)->where('id_persona', $persona);
    }

    public function leer(int $cuenta, int $persona, int $aviso): void
    {
        $destino = $this->bandeja($cuenta, $persona)->where('id_aviso', $aviso);
        abort_unless((clone $destino)->exists(), 404);
        $destino->whereNull('leido_en')->update(['leido_en' => now()]);
    }
}
