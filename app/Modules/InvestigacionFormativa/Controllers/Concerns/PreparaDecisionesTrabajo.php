<?php

namespace App\Modules\InvestigacionFormativa\Controllers\Concerns;

use App\Modules\InvestigacionFormativa\Models\Grupo;
use App\Modules\InvestigacionFormativa\Services\ConfiguracionService;
use App\Modules\InvestigacionFormativa\Services\DecisionTrabajoService;
use App\Shared\Models\Core\Seccion;

/**
 * Prepara el contexto de decisiones que necesita la vista docente del grupo.
 *
 * La vista se comparte entre GrupoController y EvaluacionController, así que
 * los datos de aprobación y trabajo destacado se generan en un solo lugar.
 */
trait PreparaDecisionesTrabajo
{
    /**
     * @return array<string, mixed>
     */
    private function datosDecisiones(
        DecisionTrabajoService $decisiones,
        ConfiguracionService $configuracion,
        Seccion $seccion,
        Grupo $grupo,
        ?object $detalle,
    ): array {
        $idSeccion = (int) $seccion->id_seccion;
        $idGrupo = (int) $grupo->id_grupo;
        $estadoSeccion = $decisiones->estadoDeSeccion($idSeccion);
        $idProyecto = $detalle?->proyecto?->id_proyecto;

        return [
            'estadoDecision' => $estadoSeccion->por_grupo[$idGrupo] ?? null,
            'seleccion' => $estadoSeccion->seleccion,
            'versionAprobacion' => $idProyecto === null
                ? 0
                : $decisiones->versionAprobacion($idSeccion, (int) $idProyecto),
            'versionSeleccion' => $decisiones->versionSeleccion($idSeccion),
            'historialAprobacion' => $idProyecto === null
                ? []
                : $decisiones->historialAprobacion((int) $idProyecto),
            'historialSeleccion' => $decisiones->historialSeleccion($idSeccion),
            'puedeDecidir' => $configuracion->estado($idSeccion)->habilitada
                && $configuracion->estaConfigurada($idSeccion)
                && $seccion->estado === 'Activa',
        ];
    }
}
