<?php

namespace App\Modules\Bienestar\Adapters;

use App\Modules\Bienestar\Contracts\FuenteAcademicaInterface;
use App\Modules\Bienestar\DTO\RegistroAcademicoDTO;
use Illuminate\Support\Facades\DB;

final class AdaptadorMatriculaLocal implements FuenteAcademicaInterface
{
    /**
     * @return list<RegistroAcademicoDTO>
     */
    public function obtenerRegistrosPeriodo(string $periodo): array
    {
        return [
            // Estudiante 1021400121 (alumno@portal.local) - Caso regular, sin riesgo
            new RegistroAcademicoDTO(
                codigoEstudiante: '1021400121',
                periodo: $periodo,
                curso: 'Sistemas Distribuidos',
                vecesCursado: 1,
                porcentajeInasistencia: 5.0,
                creditosMatriculadosPeriodo: 20,
                creditosPendientesEgreso: 45,
                promedioPonderado: 15.2,
            ),

            // Estudiante 1021400222 (Carlos Méndez) - Caso de 3ra matrícula en Estructura de Datos
            new RegistroAcademicoDTO(
                codigoEstudiante: '1021400222',
                periodo: $periodo,
                curso: 'Estructura de Datos',
                vecesCursado: 3,
                porcentajeInasistencia: 15.0,
                creditosMatriculadosPeriodo: 16,
                creditosPendientesEgreso: 60,
                promedioPonderado: 10.4,
            ),

            // Estudiante 1021400323 (Lucía Paredes) - Caso de inasistencia límite preventiva 20.00%
            new RegistroAcademicoDTO(
                codigoEstudiante: '1021400323',
                periodo: $periodo,
                curso: 'Ingeniería de Software I',
                vecesCursado: 1,
                porcentajeInasistencia: 20.0,
                creditosMatriculadosPeriodo: 18,
                creditosPendientesEgreso: 80,
                promedioPonderado: 12.0,
            ),

            // Estudiante 1021400424 (Juan Pérez) - Caso de inhabilitación por inasistencia 30.01%
            new RegistroAcademicoDTO(
                codigoEstudiante: '1021400424',
                periodo: $periodo,
                curso: 'Base de Datos II',
                vecesCursado: 2,
                porcentajeInasistencia: 30.01,
                creditosMatriculadosPeriodo: 14,
                creditosPendientesEgreso: 50,
                promedioPonderado: 09.0,
            ),

            // Caso borde: Estudiante que viene de matrícula pero no existe en core.estudiante
            new RegistroAcademicoDTO(
                codigoEstudiante: '9999999999',
                periodo: $periodo,
                curso: 'Redes de Comunicación',
                vecesCursado: 3,
                porcentajeInasistencia: 10.0,
                creditosMatriculadosPeriodo: 14,
                creditosPendientesEgreso: 40,
                promedioPonderado: 08.0,
            ),
        ];
    }

    /**
     * @return list<array{codigo: string, nombres: string, apellidos: string, curso: string, creditos: int}>
     */
    public function obtenerEstudiantesDocente(string $codigoDocente, string $periodo): array
    {
        // Si el docente es DOC-PRF-001, DOC-001 o DOC-DIR-001, retornamos los estudiantes de sus cursos
        if (in_array($codigoDocente, ['DOC-PRF-001', 'DOC-001', 'DOC-DIR-001'], true)) {
            return [
                [
                    'codigo' => '1021400121',
                    'nombres' => 'Estudiante',
                    'apellidos' => 'Alumno Demo',
                    'curso' => 'Ingeniería de Software I',
                    'creditos' => 4,
                ],
                [
                    'codigo' => '1021400222',
                    'nombres' => 'Carlos',
                    'apellidos' => 'Méndez Soto',
                    'curso' => 'Ingeniería de Software I',
                    'creditos' => 4,
                ],
                [
                    'codigo' => '1021400323',
                    'nombres' => 'Lucía',
                    'apellidos' => 'Paredes Ruiz',
                    'curso' => 'Ingeniería de Software I',
                    'creditos' => 4,
                ],
                [
                    'codigo' => '1021400424',
                    'nombres' => 'Juan',
                    'apellidos' => 'Pérez Castro',
                    'curso' => 'Ingeniería de Software I',
                    'creditos' => 4,
                ],
            ];
        }

        return [];
    }

    public function buscarEstudiantePorCodigo(string $codigoEstudiante): ?object
    {
        $estudiante = DB::table('core.estudiante as e')
            ->join('core.persona as p', 'p.id_persona', '=', 'e.id_persona')
            ->leftJoin('core.escuela_profesional as ep', 'ep.id_escuela', '=', 'e.id_escuela')
            ->where('e.codigo_universitario', $codigoEstudiante)
            ->select([
                'e.id_estudiante',
                'e.codigo_universitario as codigo',
                'p.nombres',
                'p.apellido_paterno',
                'p.apellido_materno',
                'ep.nombre as escuela',
            ])
            ->first();

        if (! $estudiante) {
            return null;
        }

        return (object) [
            'id_estudiante' => (int) $estudiante->id_estudiante,
            'codigo' => (string) $estudiante->codigo,
            'nombres' => (string) $estudiante->nombres,
            'apellidos' => trim("{$estudiante->apellido_paterno} {$estudiante->apellido_materno}"),
            'escuela' => (string) ($estudiante->escuela ?? 'Escuela de Informática'),
        ];
    }
}
