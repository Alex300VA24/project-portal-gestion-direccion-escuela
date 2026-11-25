<?php

namespace App\Modules\GestionHorarios\Services;

use App\Modules\GestionHorarios\Repositories\ConsultaAmbientesRepository;
use App\Modules\GestionHorarios\Repositories\OfertaAcademicaRepository;
use Illuminate\Database\ConnectionInterface;

final class ConsultaAmbientes
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function consultar(?int $periodo, ?int $ambiente, string $buscar, string $tipo): array
    {
        $repo = new ConsultaAmbientesRepository($this->db);
        $periodos = (new OfertaAcademicaRepository($this->db))->periodos();
        $periodoValido = $periodo !== null && $periodos->contains('id_periodo', $periodo);
        $datos = ['periodos' => $periodos, 'periodoValido' => $periodoValido, 'tipos' => $repo->tipos(), 'ambientes' => $repo->ambientes($buscar, $tipo), 'seleccion' => null, 'bloqueos' => collect(), 'actividades' => collect(), 'bloques' => collect(), 'restricciones' => collect()];
        if ($periodoValido && $ambiente !== null && ($seleccion = $repo->ambiente($ambiente))) {
            $datos['seleccion'] = $seleccion;
            $datos['bloqueos'] = $repo->bloqueos($periodo, $ambiente);
            $datos['actividades'] = $repo->actividades($periodo, $ambiente);
            $datos['bloques'] = $repo->bloques($periodo);
            $datos['restricciones'] = $repo->restricciones($ambiente);
        }

        return $datos;
    }
}
