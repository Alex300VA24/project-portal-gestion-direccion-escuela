<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Repositories\ConciliacionOfertaSeccionRepository;
use Illuminate\Database\ConnectionInterface;

/** Inspecciona referencias sin alterarlas; no asegura su estado después de la lectura. */
final class ConciliacionOfertaSeccion
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function evaluar(int $periodo): array
    {
        if ($this->db->getDriverName() === 'pgsql' && $this->db->transactionLevel() === 0) {
            return $this->db->transaction(function () use ($periodo) {
                $this->db->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');

                return $this->leer($periodo);
            });
        }

        return $this->leer($periodo);
    }

    private function leer(int $periodo): array
    {
        $repo = new ConciliacionOfertaSeccionRepository($this->db);
        if (! $repo->disponible()) {
            return ['comprobable' => false, 'motivo' => 'MIGRACION_PENDIENTE'];
        }
        $asociaciones = $repo->asociaciones($periodo);
        $secciones = $repo->secciones($periodo);
        $ofertas = $repo->ofertas($periodo);
        $vigentesPorSeccion = [];
        $ofertasConSeccion = [];
        $filas = [];
        foreach ($asociaciones as $fila) {
            $causas = [];
            if ($fila->seccion_existente === null) {
                $causas[] = 'SECCION_INEXISTENTE';
            }
            if ($fila->oferta_existente === null) {
                $causas[] = 'OFERTA_INEXISTENTE';
            }
            if ($fila->seccion_existente !== null && $fila->oferta_existente !== null) {
                if ($fila->seccion_periodo === null || $fila->oferta_periodo === null) {
                    $causas[] = 'PERIODO_NO_COMPROBABLE';
                } elseif ((string) $fila->seccion_periodo !== (string) $fila->oferta_periodo) {
                    $causas[] = 'PERIODO_INCOMPATIBLE';
                }
                if ($fila->seccion_curso === null || $fila->oferta_curso === null) {
                    $causas[] = 'CURSO_NO_COMPROBABLE';
                } elseif ((string) $fila->seccion_curso !== (string) $fila->oferta_curso) {
                    $causas[] = 'CURSO_INCOMPATIBLE';
                }
            }
            if ($fila->estado === 'VIGENTE') {
                $vigentesPorSeccion[(string) $fila->id_seccion] = true;
                if (! $causas) {
                    $ofertasConSeccion[(string) $fila->id_oferta] = true;
                }
            }
            $filas[] = ['id_asociacion' => (string) $fila->id_oferta_seccion, 'id_seccion' => (string) $fila->id_seccion, 'id_oferta' => (string) $fila->id_oferta, 'estado' => $fila->estado, 'causas' => $causas];
        }

        return ['comprobable' => true, 'asociaciones' => $filas,
            'secciones_sin_vigente' => $secciones->filter(fn ($s) => ! isset($vigentesPorSeccion[(string) $s->id_seccion]))->pluck('id_seccion')->values()->all(),
            'ofertas_sin_secciones_validas' => $ofertas->filter(fn ($o) => ! isset($ofertasConSeccion[(string) $o->id_oferta]))->pluck('id_oferta')->values()->all()];
    }
}
