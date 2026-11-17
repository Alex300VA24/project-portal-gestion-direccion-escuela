<?php

namespace App\Modules\Bienestar\Contracts;

use App\Modules\Bienestar\DTO\RegistroAcademicoDTO;

interface FuenteAcademicaInterface
{
    /**
     * Retorna lista de registros académicos para evaluación automática de riesgo en un periodo.
     *
     * @return list<RegistroAcademicoDTO>
     */
    public function obtenerRegistrosPeriodo(string $periodo): array;

    /**
     * Retorna estudiantes a cargo de un docente en un periodo dado.
     *
     * @return list<array{codigo: string, nombres: string, apellidos: string, curso: string, creditos: int}>
     */
    public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array;

    /**
     * Búsqueda exacta de un estudiante por su código universitario.
     *
     * @return object{codigo: string, nombres: string, apellidos: string, escuela: string}|null
     */
    public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object;
}
