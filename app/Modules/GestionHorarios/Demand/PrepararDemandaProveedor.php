<?php

namespace App\Modules\GestionHorarios\Demand;

use App\Modules\GestionHorarios\Contracts\IdentidadCursoPlan;
use App\Modules\GestionHorarios\Contracts\ProveedorDemandaAcademica;
use DateTimeImmutable;
use DomainException;

/** Validación sin escrituras: la recepción institucional requiere persistir la procedencia. */
final class PrepararDemandaProveedor
{
    public function __construct(private readonly ProveedorDemandaAcademica $proveedor, private readonly IdentidadCursoPlan $catalogo) {}

    public function preparar(int $escuela, int $periodo): array
    {
        if ($escuela < 1 || $periodo < 1) {
            throw new DomainException('Ámbito académico inválido.');
        }
        $datos = $this->proveedor->obtener($escuela, $periodo);
        if (($datos['escuela'] ?? null) !== $escuela || ($datos['periodo'] ?? null) !== $periodo || ($datos['completa'] ?? null) !== true || ! is_string($datos['fuente'] ?? null) || trim($datos['fuente']) === '' || ! is_int($datos['version'] ?? null) || $datos['version'] < 1 || ! is_string($datos['capturada_en'] ?? null) || ! is_array($datos['cursos'] ?? null) || ! array_is_list($datos['cursos'])) {
            throw new DomainException('La fuente no acredita una captura completa y versionada para el ámbito solicitado.');
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $datos['capturada_en']);
        if (! $fecha || $fecha->format('Y-m-d\TH:i:sP') !== $datos['capturada_en']) {
            throw new DomainException('La captura debe indicar una fecha y zona horaria verificables.');
        }
        foreach ($datos['cursos'] as $curso) {
            if (! is_array($curso) || ! is_int($curso['id_plan'] ?? null) || $curso['id_plan'] < 1 || ! is_int($curso['id_curso'] ?? null) || $curso['id_curso'] < 1 || ! is_int($curso['id_curso_plan'] ?? null) || $curso['id_curso_plan'] < 1 || ! $this->catalogo->coincide($escuela, $curso['id_plan'], $curso['id_curso'], $curso['id_curso_plan'])) {
                throw new DomainException('La identidad de curso-plan no coincide con la escuela, el plan y el curso.');
            }
        }
        $instantanea = InstantaneaDemanda::desdeArray(['periodo' => $periodo, 'version' => $datos['version'], 'cursos' => $datos['cursos']]);

        return ['instantanea' => $instantanea, 'procedencia' => ['fuente' => $datos['fuente'], 'escuela' => $escuela, 'periodo' => $periodo, 'version' => $datos['version'], 'capturada_en' => $datos['capturada_en']], 'estado' => 'VALIDADA_SIN_PERSISTIR'];
    }
}
