<?php

namespace App\Modules\Bienestar\Services;

use App\Modules\Bienestar\Contracts\FuenteAcademicaInterface;
use App\Modules\Bienestar\DTO\RegistroAcademicoDTO;
use App\Modules\Bienestar\Models\AlertaRiesgo;
use App\Shared\Models\Core\Estudiante;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RiesgoAcademicoService
{
    public function __construct(
        private readonly FuenteAcademicaInterface $fuenteAcademica,
    ) {}

    /**
     * Ejecuta el análisis de riesgo para un periodo y sincroniza las alertas automáticas.
     *
     * @return array{procesados: int, creadas: int, reabiertas: int, resueltas: int, omitidos: int}
     */
    public function detectarYActualizarAlertas(string $periodo): array
    {
        $registros = $this->fuenteAcademica->obtenerRegistrosPeriodo($periodo);

        $stats = [
            'procesados' => 0,
            'creadas' => 0,
            'reabiertas' => 0,
            'resueltas' => 0,
            'omitidos' => 0,
        ];

        // Agrupación por estudiante para evaluar la regla de créditos una sola vez (curso = 'GLOBAL')
        /** @var array<string, list<RegistroAcademicoDTO>> $porEstudiante */
        $porEstudiante = [];
        foreach ($registros as $dto) {
            $porEstudiante[$dto->codigoEstudiante][] = $dto;
        }

        $condicionesDetectadas = []; // Clave: "idEstudiante:curso:regla"

        foreach ($porEstudiante as $codigoEstudiante => $dtos) {
            $stats['procesados']++;

            // Verificar si el estudiante existe en core.estudiante
            $estudiante = Estudiante::where('codigo_universitario', $codigoEstudiante)->first();
            if (! $estudiante) {
                Log::warning("Bienestar RiesgoAcademico: Estudiante con código {$codigoEstudiante} proveniente de matrícula no existe en core.estudiante. Omitiendo.");
                $stats['omitidos']++;

                continue;
            }

            $idEstudiante = (int) $estudiante->id_estudiante;

            // 1. Evaluación de Cursos individuales (reiterancia e inasistencias)
            foreach ($dtos as $dto) {
                // a) Desaprobación / Reiterancia
                if ($dto->vecesCursado >= 4) {
                    $key = "{$idEstudiante}:{$dto->curso}:cuarta_matricula";
                    $condicionesDetectadas[$key] = [
                        'id_estudiante' => $idEstudiante,
                        'curso' => $dto->curso,
                        'regla' => 'cuarta_matricula',
                        'prioridad' => 'alta',
                        'detalles' => [
                            'veces_cursado' => $dto->vecesCursado,
                            'motivo' => 'Cuarta matrícula cursada (Art. 102 Ley 30220: causal de retiro definitivo)',
                        ],
                    ];
                } elseif ($dto->vecesCursado === 3) {
                    $key = "{$idEstudiante}:{$dto->curso}:tercera_matricula";
                    $condicionesDetectadas[$key] = [
                        'id_estudiante' => $idEstudiante,
                        'curso' => $dto->curso,
                        'regla' => 'tercera_matricula',
                        'prioridad' => 'alta',
                        'detalles' => [
                            'veces_cursado' => $dto->vecesCursado,
                            'motivo' => 'Tercera matrícula cursada (Art. 102 Ley 30220: separación temporal un año)',
                        ],
                    ];
                }

                // b) Inasistencias (> 30.0% inhabilitación; >= 20.0% preventiva)
                if ($dto->porcentajeInasistencia > 30.0) {
                    $key = "{$idEstudiante}:{$dto->curso}:inasistencia_inhabilitacion";
                    $condicionesDetectadas[$key] = [
                        'id_estudiante' => $idEstudiante,
                        'curso' => $dto->curso,
                        'regla' => 'inasistencia_inhabilitacion',
                        'prioridad' => 'alta',
                        'detalles' => [
                            'porcentaje_inasistencia' => $dto->porcentajeInasistencia,
                            'motivo' => 'Inasistencias superiores al 30% (inhabilitación y retiro automático)',
                        ],
                    ];
                } elseif ($dto->porcentajeInasistencia >= 20.0) {
                    $key = "{$idEstudiante}:{$dto->curso}:inasistencia_preventiva";
                    $condicionesDetectadas[$key] = [
                        'id_estudiante' => $idEstudiante,
                        'curso' => $dto->curso,
                        'regla' => 'inasistencia_preventiva',
                        'prioridad' => 'media',
                        'detalles' => [
                            'porcentaje_inasistencia' => $dto->porcentajeInasistencia,
                            'motivo' => 'Inasistencias preventivas entre 20% y 30%',
                        ],
                    ];
                }
            }

            // 2. Evaluación de Créditos Semestrales (una sola vez por estudiante, curso = 'GLOBAL')
            $primerDto = $dtos[0];
            $creditos = $primerDto->creditosMatriculadosPeriodo;
            $creditosPendientes = $primerDto->creditosPendientesEgreso;

            if ($creditos < 12) {
                // Exención: si los créditos pendientes de egreso son menores a 12, está exento.
                if ($creditosPendientes !== null && $creditosPendientes < 12) {
                    // Exento por ser egresante, no se genera alerta
                } else {
                    $porVerificar = ($creditosPendientes === null);
                    $key = "{$idEstudiante}:GLOBAL:creditos_insuficientes";
                    $condicionesDetectadas[$key] = [
                        'id_estudiante' => $idEstudiante,
                        'curso' => 'GLOBAL',
                        'regla' => 'creditos_insuficientes',
                        'prioridad' => 'media',
                        'detalles' => [
                            'creditos_matriculados' => $creditos,
                            'creditos_pendientes_egreso' => $creditosPendientes,
                            'estado_egreso' => $porVerificar ? 'por_verificar' : 'no_egresante',
                            'motivo' => $porVerificar
                                ? 'Matrícula menor a 12 créditos (Art. 99.8 Ley 30220). Condición de egreso: pendiente de verificar.'
                                : 'Matrícula menor a 12 créditos (Art. 99.8 Ley 30220). Pérdida de condición regular.',
                        ],
                    ];
                }
            }
        }

        // 3. Sincronizar en base de datos dentro de transacción
        DB::transaction(function () use ($periodo, $condicionesDetectadas, &$stats) {
            // Obtener todas las alertas automáticas existentes para este periodo
            $alertasExistentes = AlertaRiesgo::where('periodo', $periodo)
                ->where('origen', 'automatica')
                ->get();

            $alertasMap = [];
            foreach ($alertasExistentes as $alerta) {
                $k = "{$alerta->id_estudiante}:{$alerta->curso}:{$alerta->regla}";
                $alertasMap[$k] = $alerta;
            }

            // Procesar condiciones activas actuales
            foreach ($condicionesDetectadas as $key => $cond) {
                if (isset($alertasMap[$key])) {
                    $alerta = $alertasMap[$key];
                    // Si estaba resuelta automáticamente y la condición reaparece, se reabre para revisión de Secretaría
                    if ($alerta->estado === 'resuelta_automaticamente') {
                        $alerta->update([
                            'estado' => 'en_revision',
                            'fecha_resolucion' => null,
                            'detalles' => $cond['detalles'],
                        ]);
                        $stats['reabiertas']++;
                    }
                    // Si está 'atendida' o 'descartada', NUNCA se reabre (regla obligatoria)
                } else {
                    // Alerta nueva: pasa a la bandeja de Secretaría en estado 'en_revision'
                    AlertaRiesgo::create([
                        'id_estudiante' => $cond['id_estudiante'],
                        'periodo' => $periodo,
                        'curso' => $cond['curso'],
                        'regla' => $cond['regla'],
                        'origen' => 'automatica',
                        'prioridad' => $cond['prioridad'],
                        'estado' => 'en_revision',
                        'detalles' => $cond['detalles'],
                        'fecha_deteccion' => now(),
                    ]);
                    $stats['creadas']++;
                }
            }

            // Procesar alertas que ya no cumplen la condición en este periodo
            foreach ($alertasExistentes as $alerta) {
                $k = "{$alerta->id_estudiante}:{$alerta->curso}:{$alerta->regla}";
                if (! isset($condicionesDetectadas[$k])) {
                    // Si ya no existe la condición:
                    // Solo marcar como resuelta_automaticamente si estaba en 'en_revision', 'remitida_bienestar' o 'en_atencion'
                    if (in_array($alerta->estado, ['en_revision', 'remitida_bienestar', 'pendiente', 'en_atencion'], true)) {
                        $alerta->update([
                            'estado' => 'resuelta_automaticamente',
                            'fecha_resolucion' => now(),
                        ]);
                        $stats['resueltas']++;
                    }
                }
            }
        });

        return $stats;
    }
}
