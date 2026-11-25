<?php

namespace App\Modules\GestionHorarios\Repositories;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;

final class ConciliacionOfertaSeccionRepository
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function disponible(): bool
    {
        return $this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion');
    }

    public function asociaciones(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.oferta_seccion as os')
            ->leftJoin('core.seccion as s', 's.id_seccion', '=', 'os.id_seccion')
            ->leftJoin('gestion_horarios.oferta_curso as o', 'o.id_oferta', '=', 'os.id_oferta')
            ->where(fn ($q) => $q->where('s.id_periodo', $periodo)->orWhere('o.id_periodo', $periodo)
                ->orWhere(fn ($q) => $q->whereNull('s.id_seccion')->whereNull('o.id_oferta')))
            ->orderBy('os.id_oferta_seccion')
            ->get(['os.id_oferta_seccion', 'os.id_oferta', 'os.id_seccion', 'os.estado', 's.id_seccion as seccion_existente', 'o.id_oferta as oferta_existente', 's.id_periodo as seccion_periodo', 's.id_curso_plan as seccion_curso', 'o.id_periodo as oferta_periodo', 'o.id_curso_plan as oferta_curso', 'o.tipo_oferta']);
    }

    public function secciones(int $periodo): Collection
    {
        return $this->db->table('core.seccion')->where('id_periodo', $periodo)->orderBy('id_seccion')->get(['id_seccion']);
    }

    public function ofertas(int $periodo): Collection
    {
        return $this->db->table('gestion_horarios.oferta_curso')->where('id_periodo', $periodo)->orderBy('id_oferta')->get(['id_oferta']);
    }
}
