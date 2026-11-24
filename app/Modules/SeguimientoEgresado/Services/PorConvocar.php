<?php

namespace App\Modules\SeguimientoEgresado\Services;

use App\Modules\SeguimientoEgresado\Models\CampaniaSeguimiento;
use App\Modules\SeguimientoEgresado\Models\Egresado;

/**
 * Cuántos egresados faltan por invitar a una campaña de egresados abierta.
 *
 * «x de y respondieron» cuenta solo a quienes ya recibieron su invitación; hasta que se pulsa «Enviar invitaciones»
 * la campaña puede parecer completa (o vacía: «0 de 0») aunque haya más egresados por convocar. Este número es el que
 * completa esa lectura. Usa la misma regla que «Enviar invitaciones»: egresados confirmados de las promociones de la
 * campaña, con correo personal y todavía sin invitación en ella.
 */
class PorConvocar
{
    /** null cuando no aplica (campaña de empleadores o que ya no está abierta: no se puede convocar). */
    public function de(CampaniaSeguimiento $campania): ?int
    {
        if ($campania->tipo !== 'EGRESADOS' || $campania->estado !== 'ABIERTA') {
            return null;
        }

        $promociones = $campania->cohortes->pluck('id');
        if ($promociones->isEmpty()) {
            return 0;
        }

        return Egresado::query()
            ->whereIn('id_cohorte', $promociones)
            ->whereHas('estadoVigente.estadoAcademico', fn ($q) => $q->whereIn('codigo', CatalogosDeCampania::ETAPAS_EGRESADO))
            ->whereHas('contactoVigente', fn ($q) => $q->whereNotNull('correo_personal')->where('correo_personal', '<>', ''))
            ->whereDoesntHave('invitaciones', fn ($q) => $q->where('id_campania', $campania->id))
            ->count();
    }
}
