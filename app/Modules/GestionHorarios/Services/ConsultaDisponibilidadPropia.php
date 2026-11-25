<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorIdentidadDocente;
use App\Modules\GestionHorarios\Repositories\ConsultaDisponibilidadDocenteRepository;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Throwable;

/** Solo lectura: el docente se obtiene exclusivamente del proveedor de identidad. */
final class ConsultaDisponibilidadPropia
{
    public function __construct(private readonly ConnectionInterface $db, private readonly ?ProveedorIdentidadDocente $identidad = null) {}

    public function consultar(User $usuario, ?int $periodo): array
    {
        try {
            $identidad = $this->identidad ?? (app()->bound(ProveedorIdentidadDocente::class) ? app(ProveedorIdentidadDocente::class) : null);
            $docente = $identidad?->docenteId($usuario);
        } catch (Throwable $e) {
            // No consultar asignaciones si la vinculación del guard no se pudo resolver.
            report($e);

            return ['acceso' => false, 'periodos' => collect(), 'periodo_valido' => false];
        }
        if ($docente === null || $docente <= 0) {
            return ['acceso' => false, 'periodos' => collect(), 'periodo_valido' => false];
        }
        $base = $this->db->table('gestion_horarios.asignacion_docente as ad')
            ->join('core.docente as doc', 'doc.id_docente', '=', 'ad.id_docente')
            ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'ad.id_actividad')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->join('gestion_horarios.oferta_seccion as os', function ($join) {
                $join->on('os.id_seccion', '=', 's.id_seccion')->where('os.estado', '=', 'VIGENTE');
            })
            ->join('gestion_horarios.oferta_curso as o', function ($join) {
                $join->on('o.id_oferta', '=', 'os.id_oferta')->on('o.id_periodo', '=', 's.id_periodo')->on('o.id_curso_plan', '=', 's.id_curso_plan');
            })
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 's.id_periodo')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->where('ad.id_docente', $docente)->where('doc.estado', 'ACTIVO')->where('ad.estado', 'CONFIRMADA')
            ->where('s.estado', 'ACTIVA')->where('o.estado', 'OFERTADA');
        $periodos = (clone $base)->distinct()->orderByDesc('p.fecha_inicio')->get(['p.id_periodo', 'p.codigo', 'p.fecha_inicio']);
        $valido = $periodo !== null && $periodo > 0 && $periodos->contains(fn ($p) => (int) $p->id_periodo === $periodo);
        $resultado = ['acceso' => true, 'periodos' => $periodos, 'periodo_valido' => $valido, 'asignaciones' => collect(), 'declaracion' => null, 'franjas' => collect(), 'estado' => null];
        if (! $valido) {
            return $resultado;
        }
        $resultado['asignaciones'] = (clone $base)->where('s.id_periodo', $periodo)->orderBy('c.nombre')->get(['ad.id_asignacion', 'a.tipo_actividad', 'c.codigo as curso', 'c.nombre', 's.codigo as seccion', 'cp.ciclo']);
        $repo = new ConsultaDisponibilidadDocenteRepository($this->db);
        $declaraciones = $repo->declaraciones($periodo, $docente);
        $franjas = $repo->registros($periodo, $docente);
        $resultado['declaracion'] = $declaraciones->count() === 1 ? $declaraciones->first() : null;
        $resultado['franjas'] = $franjas;
        $resultado['estado'] = $this->estado($declaraciones, $franjas, $periodo);

        return $resultado;
    }

    private function estado(Collection $declaraciones, Collection $franjas, int $periodo): string
    {
        if ($declaraciones->count() > 1) {
            return 'REQUIERE_REVISION';
        }
        $declaracion = $declaraciones->first();
        if (! $declaracion) {
            return $franjas->isEmpty() ? 'SIN_DECLARACION' : 'REQUIERE_REVISION';
        }
        if (! in_array($declaracion->estado, ['PENDIENTE', 'PRESENTADA', 'NO_PRESENTADA'], true) || ($declaracion->estado === 'PRESENTADA' && ! $declaracion->presentado_en)) {
            return 'REQUIERE_REVISION';
        }
        foreach ($franjas as $f) {
            if ((string) $f->id_declaracion !== (string) $declaracion->id_declaracion || (int) $f->periodo_bloque !== $periodo || ! in_array($f->dia_semana, ['LUNES', 'MARTES', 'MIERCOLES', 'JUEVES', 'VIERNES', 'SABADO'], true) || ! $f->hora_inicio || ! $f->hora_fin || $f->hora_inicio >= $f->hora_fin) {
                return 'REQUIERE_REVISION';
            }
        }
        if ($declaracion->estado !== 'PRESENTADA') {
            return $franjas->isEmpty() ? 'NO_PRESENTADA' : 'REQUIERE_REVISION';
        }

        return $franjas->isEmpty() ? 'PRESENTADA_SIN_FRANJAS' : 'PRESENTADA_CON_FRANJAS';
    }
}
