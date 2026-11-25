<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use App\Modules\GestionHorarios\Repositories\ConsultaSeccionesRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use Illuminate\Database\ConnectionInterface;

final class ConsultaDistribucionSecciones
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(?int $periodo, string $buscar, string $ciclo, ?int $plan = null): array
    {
        $periodosRepo = new OfertaAcademicaRepository($this->db);
        $periodos = $plan !== null ? $periodosRepo->periodosParaPlan($plan) : $periodosRepo->periodos();
        if (! $periodo || ! $periodos->contains('id_periodo', $periodo)) {
            return ['periodos' => $periodos, 'demanda' => null, 'cursos' => collect(), 'integridad' => null, 'asociacionDisponible' => $this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion')];
        }
        $demanda = (new RepositorioDemandaPostgres($this->db))->vigente($periodo);
        $cursos = (new ConsultaSeccionesRepository($this->db))->cursos($periodo, $demanda ? (int) $demanda->id_demanda : null, $buscar, $ciclo, $plan);
        foreach ($cursos as $curso) {
            $secciones = $curso->secciones;
            $capacidadesCompletas = $secciones->isNotEmpty() && $secciones->every(fn ($s) => $s->capacidad !== null && (int) $s->capacidad > 0);
            $curso->capacidad_registrada = $capacidadesCompletas ? $secciones->sum(fn ($s) => (int) $s->capacidad) : null;
            $curso->diferencia = $curso->total !== null && $curso->capacidad_registrada !== null ? (int) $curso->total - $curso->capacidad_registrada : null;
        }

        $asociacionDisponible = $this->db->getSchemaBuilder()->hasTable('gestion_horarios.oferta_seccion');
        // La conciliación existente examina todo el periodo y no acredita alcance por escuela.
        $integridad = null;

        return compact('periodos', 'demanda', 'cursos', 'asociacionDisponible', 'integridad');
    }
}
