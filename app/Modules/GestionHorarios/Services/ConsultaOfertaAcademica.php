<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Demand\RepositorioDemandaPostgres;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use Illuminate\Database\ConnectionInterface;

final class ConsultaOfertaAcademica
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(?int $periodo, string $buscar, string $ciclo, string $estado, string $tipo = '', ?int $plan = null): array
    {
        $repo = new OfertaAcademicaRepository($this->db);
        $periodos = $plan !== null ? $repo->periodosParaPlan($plan) : $repo->periodos();
        if ($periodo === null || ! $periodos->contains('id_periodo', $periodo)) {
            return ['periodos' => $periodos, 'catalogo' => $repo->catalogo($plan), 'demanda' => null, 'cursos' => collect()];
        }
        $demanda = (new RepositorioDemandaPostgres($this->db))->vigente($periodo);

        return ['periodos' => $periodos, 'catalogo' => $repo->catalogo($plan), 'demanda' => $demanda, 'cursos' => $repo->cursos($periodo, $demanda ? (int) $demanda->id_demanda : null, $buscar, $ciclo, $estado, $tipo, $plan)];
    }
}
