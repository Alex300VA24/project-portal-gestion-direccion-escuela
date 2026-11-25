<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Repositories\ConsultaHorariosRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use Illuminate\Database\ConnectionInterface;

final class ConsultaHorarios
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(?int $periodo, ?int $horario, string $ciclo, string $seccion): array
    {
        $periodos = (new OfertaAcademicaRepository($this->db))->periodos();
        $repo = new ConsultaHorariosRepository($this->db);
        $horarios = $periodo && $periodos->contains('id_periodo', $periodo) ? $repo->horarios($periodo) : collect();
        $seleccion = $horario ? $horarios->firstWhere('id_horario', $horario) : null;
        $sesiones = $seleccion ? $repo->sesiones($periodo, $horario, $ciclo, $seccion) : collect();

        return compact('periodos', 'horarios', 'seleccion', 'sesiones') + ['docentes' => $repo->docentes($sesiones)];
    }
}
