<?php

namespace App\Modules\InvestigacionFormativa\Support;

use App\Modules\InvestigacionFormativa\Exceptions\ArchivoNoDisponible;

final class UnavailableArchivoGateway implements ArchivoGateway
{
    public function crear(array $metadata): int
    {
        throw new ArchivoNoDisponible(
            'La integración con el archivo compartido no está aprobada. No se pueden registrar entregas.',
        );
    }

    public function buscar(int $idArchivo): ?array
    {
        throw new ArchivoNoDisponible(
            'La integración con el archivo compartido no está aprobada. No se pueden descargar entregas.',
        );
    }
}
