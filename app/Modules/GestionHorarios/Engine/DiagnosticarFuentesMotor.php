<?php

namespace App\Modules\GestionHorarios\Engine;

/** Comprobación de presencia; no acredita procedencia ni autoriza ejecutar el motor. */
final class DiagnosticarFuentesMotor
{
    public function evaluar(array $mapeo, ?array $complementos): array
    {
        $diagnosticos = [];
        foreach ([
            'poblacion' => 'POBLACION_POR_SECCION_AUSENTE',
            'participantes_actividad' => 'PARTICIPANTES_DE_ACTIVIDAD_AUSENTES',
            'particiones' => 'PARTICIONES_LABORATORIO_NO_ACREDITADAS',
            'sesiones_fisicas' => 'SESIONES_FISICAS_NO_ACREDITADAS',
            'calendario' => 'CALENDARIO_COMPLETO_AUSENTE',
            'disponibilidad' => 'DISPONIBILIDAD_EFECTIVA_NO_ACREDITADA',
            'restricciones' => 'RESTRICCIONES_APLICABLES_NO_ACREDITADAS',
            'patrones' => 'PATRONES_DE_SESION_AUSENTES',
        ] as $campo => $codigo) {
            if (! is_array($complementos[$campo] ?? null)) {
                $diagnosticos[] = ['entidad' => 'complementos', 'id' => $campo, 'codigo' => $codigo];
            }
        }
        if (! is_string($complementos['fuente'] ?? null) || $complementos['fuente'] === '' || ! is_string($complementos['version'] ?? null) || $complementos['version'] === '') {
            $diagnosticos[] = ['entidad' => 'complementos', 'id' => '', 'codigo' => 'PROCEDENCIA_O_VERSION_AUSENTE'];
        }
        if (($complementos['huella_instantanea'] ?? null) !== ($mapeo['huella_instantanea'] ?? null)) {
            $diagnosticos[] = ['entidad' => 'complementos', 'id' => '', 'codigo' => 'INSTANTANEA_NO_VINCULADA'];
        }
        if (($complementos['periodo'] ?? null) !== (string) ($mapeo['periodo']['id_periodo'] ?? '')) {
            $diagnosticos[] = ['entidad' => 'complementos', 'id' => '', 'codigo' => 'PERIODO_NO_VINCULADO'];
        }

        return $diagnosticos;
    }
}
