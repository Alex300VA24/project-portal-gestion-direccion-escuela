<?php

namespace App\Modules\GestionHorarios\Demand;

use App\Modules\GestionHorarios\Contracts\CatalogoDemanda;
use App\Modules\GestionHorarios\Contracts\RepositorioDemanda;
use DomainException;

final class ProcesarDemanda
{
    public function __construct(private readonly CatalogoDemanda $catalogo, private readonly RepositorioDemanda $repositorio) {}

    /** Devuelve aplicada, duplicada o antigua. Una versión repetida con contenido distinto es un conflicto. */
    public function procesar(InstantaneaDemanda $instantanea): string
    {
        if (! $this->catalogo->periodoExiste($instantanea->periodo)) {
            throw new DomainException('Periodo institucional desconocido.');
        }
        foreach ($instantanea->cursos as $curso) {
            if (! $this->catalogo->cursoPlanExiste($curso['id_curso_plan'])) {
                throw new DomainException('Curso-plan institucional desconocido.');
            }
        }

        return $this->repositorio->conBloqueo($instantanea->periodo, function () use ($instantanea): string {
            $anterior = $this->repositorio->ultima($instantanea->periodo);
            if ($anterior !== null && $instantanea->version < $anterior['version']) {
                return 'antigua';
            }
            if ($anterior !== null && $instantanea->version === $anterior['version']) {
                if (! hash_equals($anterior['hash'], $instantanea->hash())) {
                    throw new DomainException('La versión ya existe con contenido diferente.');
                }

                return 'duplicada';
            }
            $this->repositorio->guardar($instantanea);

            return 'aplicada';
        });
    }
}
