<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConsultaRestriccionesRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(?int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.restriccion as r')
            ->leftJoin('gestion_horarios.detalle_restriccion as d', 'd.id_restriccion', '=', 'r.id_restriccion')
            ->leftJoin('core.docente as doc', 'doc.id_docente', '=', 'd.id_docente')
            ->leftJoin('core.persona as p', 'p.id_persona', '=', 'doc.id_persona')
            ->leftJoin('core.ambiente as a', 'a.id_ambiente', '=', 'd.id_ambiente')
            ->where(fn ($q) => $q->whereNull('d.id_detalle_restriccion')->orWhereIn('d.ambito', ['GLOBAL', 'DOCENTE', 'AMBIENTE'])
                ->when($periodo !== null, fn ($q) => $q->orWhere(fn ($q) => $q->where('d.ambito', 'PERIODO')->where('d.id_periodo', $periodo))))
            ->orderBy('r.codigo')->orderBy('d.id_detalle_restriccion')
            ->get(['r.id_restriccion', 'r.codigo', 'r.nombre', 'r.descripcion', 'r.tipo', 'r.severidad', 'r.activa', 'd.id_detalle_restriccion', 'd.ambito', 'd.id_periodo', 'd.prioridad', 'doc.codigo_docente', 'p.nombres', 'p.apellido_paterno', 'p.apellido_materno', 'a.codigo as codigo_ambiente', 'a.nombre as nombre_ambiente']);
    }
}
