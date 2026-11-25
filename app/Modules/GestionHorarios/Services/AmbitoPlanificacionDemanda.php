<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorAmbientesPlanificacion;
use Illuminate\Database\ConnectionInterface;
use Throwable;

final class AmbitoPlanificacionDemanda
{
    public function __construct(private readonly ConnectionInterface $db) {}

    /** @return list<int>|null Null significa que no hay autorización verificable. */
    public function ambientes(User $usuario, int $periodo, int $plan): ?array
    {
        if ($periodo < 1 || $plan < 1 || ! app()->bound(ProveedorAmbientesPlanificacion::class)) return null;
        if (! app(AmbitoPlanes::class)->contiene($usuario, $plan)) return null;
        $escuela = (int) $this->db->table('core.plan_estudios')->where('id_plan', $plan)->value('id_escuela');
        $recursos = (new AmbitoRecursosCompartidos)->consultar($usuario, $escuela, $periodo);
        if ($recursos === null) return null;
        try {
            $alcance = app(ProveedorAmbientesPlanificacion::class)->ambientesParaPlanificar($usuario, $escuela, $periodo, $plan);
            return self::validar($alcance, $escuela, $periodo, $plan, $recursos['ambientes']);
        } catch (Throwable $e) {
            report($e);
            return null;
        }
    }

    /** Valida alcance exacto y evita ampliaciones de los permisos existentes. */
    public static function validar(?array $alcance, int $escuela, int $periodo, int $plan, array $visibles): ?array
    {
        if ($escuela < 1 || $periodo < 1 || $plan < 1 || ($alcance['escuela_id'] ?? null) !== $escuela
            || ($alcance['periodo_id'] ?? null) !== $periodo || ($alcance['plan_id'] ?? null) !== $plan) return null;
        $ids = $alcance['ambientes'] ?? null;
        if (! is_array($ids) || ! array_is_list($ids)) return null;
        foreach ($ids as $id) {
            if (! is_int($id) || $id < 1 || ! in_array($id, $visibles, true)) return null;
        }
        $ids = array_values(array_unique($ids));
        sort($ids, SORT_NUMERIC);
        return $ids;
    }
}
