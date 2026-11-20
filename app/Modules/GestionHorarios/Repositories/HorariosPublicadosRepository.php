<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

final class HorariosPublicadosRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function periodos(): Collection
    {
        return $this->db->table('gestion_horarios.horario as h')
            ->join('core.periodo_academico as p', 'p.id_periodo', '=', 'h.id_periodo')
            ->where('h.estado', 'PUBLICADO')->whereNotNull('h.publicado_en')
            ->distinct()->orderByDesc('p.id_periodo')->get(['p.id_periodo', 'p.codigo']);
    }

    /** Catálogos derivados únicamente de actividades publicadas en el periodo. */
    public function catalogos(int $periodo): array
    {
        $filas = $this->base($periodo)->distinct()->get([
            'cp.ciclo', 's.codigo as seccion', 'am.id_ambiente', 'am.codigo as ambiente_codigo', 'am.nombre as ambiente_nombre',
            'doc.id_docente', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno',
        ]);
        $ciclos = $filas->pluck('ciclo')->filter(fn ($v) => $v !== null)->unique()->sort()->values();
        $secciones = $filas->filter(fn ($v) => $v->ciclo !== null && $v->seccion !== null)
            ->map(fn ($v) => ['ciclo' => (int) $v->ciclo, 'codigo' => $v->seccion])->unique(fn ($v) => $v['ciclo'].'|'.$v['codigo'])->sortBy('codigo')->values();
        $ambientes = $filas->filter(fn ($v) => $v->id_ambiente !== null)->unique('id_ambiente')
            ->map(fn ($v) => ['id' => (int) $v->id_ambiente, 'codigo' => $v->ambiente_codigo, 'nombre' => $v->ambiente_nombre])->sortBy('nombre')->values();
        $docentes = $filas->filter(fn ($v) => $v->id_docente !== null && $v->nombres !== null)->unique('id_docente')
            ->map(fn ($v) => ['id' => (int) $v->id_docente, 'nombre' => trim($v->apellido_paterno.' '.$v->apellido_materno.' '.$v->nombres)])->sortBy('nombre')->values();

        return compact('ciclos', 'secciones', 'ambientes', 'docentes');
    }

    private function base(int $periodo): Builder
    {
        return $this->db->table('gestion_horarios.detalle_horario as d')
            ->join('gestion_horarios.horario as h', 'h.id_horario', '=', 'd.id_horario')
            ->join('gestion_horarios.actividad_academica as a', 'a.id_actividad', '=', 'd.id_actividad')
            ->join('core.seccion as s', 's.id_seccion', '=', 'a.id_seccion')
            ->join('core.curso_plan as cp', 'cp.id_curso_plan', '=', 's.id_curso_plan')
            ->join('core.curso as c', 'c.id_curso', '=', 'cp.id_curso')
            ->leftJoin('core.plan_estudios as pe', 'pe.id_plan', '=', 'cp.id_plan')
            ->leftJoin('core.ambiente as am', 'am.id_ambiente', '=', 'd.id_ambiente')
            ->leftJoin('gestion_horarios.asignacion_docente as ad', function ($join) {
                $join->on('ad.id_actividad', '=', 'a.id_actividad')->where('ad.estado', '=', 'CONFIRMADA');
            })
            ->leftJoin('core.docente as doc', 'doc.id_docente', '=', 'ad.id_docente')
            ->leftJoin('core.persona as p', 'p.id_persona', '=', 'doc.id_persona')
            ->where('h.estado', 'PUBLICADO')->whereNotNull('h.publicado_en')
            ->where('h.id_periodo', $periodo)->where('s.id_periodo', $periodo);
    }

    public function sesiones(int $periodo, string $modalidad, int $valor, ?string $seccion): Collection
    {
        $query = $this->base($periodo);

        match ($modalidad) {
            'ciclo' => $query->where('cp.ciclo', $valor),
            'ambiente' => $query->where('d.id_ambiente', $valor),
            'docente' => $query->where('ad.id_docente', $valor),
            default => throw new \InvalidArgumentException('Modalidad inválida.'),
        };
        if ($modalidad === 'ciclo' && $seccion !== null && $seccion !== '') {
            $query->where('s.codigo', $seccion);
        }

        return $query->orderBy('a.dia_semana')->orderBy('a.hora_inicio')->orderBy('a.id_actividad')
            ->get(['a.dia_semana', 'a.hora_inicio', 'a.hora_fin', 'a.tipo_actividad', 'h.ambito', 'h.version', 'pe.nombre as plan_nombre', 'cp.ciclo', 's.codigo as seccion', 'c.codigo as curso_codigo', 'c.nombre as curso_nombre', 'am.nombre as ambiente', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno']);
    }
}
