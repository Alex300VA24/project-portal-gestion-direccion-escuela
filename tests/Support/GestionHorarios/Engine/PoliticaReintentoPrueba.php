<?php

namespace Tests\Support\GestionHorarios\Engine;

/** Política conservadora del ensayo: ningún resultado autoriza por sí solo un reintento. */
final class PoliticaReintentoPrueba
{
    public function evaluar(?string $estadoSolver): string
    {
        return match ($estadoSolver) {
            null, 'OPTIMAL', 'FEASIBLE' => 'REQUIERE_TERMINACION_VERIFICADA',
            'UNKNOWN' => 'REVISION_MANUAL',
            'INFEASIBLE', 'MODEL_INVALID', 'INPUT_INVALID' => 'NO_REINTENTAR',
            default => 'ESTADO_NO_RECONOCIDO',
        };
    }
}
