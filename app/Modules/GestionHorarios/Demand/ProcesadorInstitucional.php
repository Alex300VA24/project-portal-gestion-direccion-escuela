<?php

namespace App\Modules\GestionHorarios\Demand;

use Illuminate\Database\ConnectionInterface;

final class ProcesadorInstitucional
{
    public function __construct(private readonly ConnectionInterface $db) {}

    public function procesar(InstantaneaDemanda $instantanea): string
    {
        return (new ProcesarDemanda(new CatalogoInstitucional($this->db), new RepositorioDemandaPostgres($this->db)))->procesar($instantanea);
    }
}
