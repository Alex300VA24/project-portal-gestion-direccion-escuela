<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorAlcanceRecursosAcademicos;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use App\Modules\GestionHorarios\Policies\RegistroPolicy;
use Throwable;

/**
 * Resuelve únicamente IDs de recursos para una escuela y periodo comprobados.
 * No autoriza a leer disponibilidad, restricciones, ocupación ni sesiones globales.
 */
final class AmbitoRecursosCompartidos
{
    public function __construct(
        private readonly ?ProveedorEscuelasAutorizadas $escuelas = null,
        private readonly ?ProveedorAlcanceRecursosAcademicos $recursos = null,
    ) {}

    /**
     * @return array{escuela_id: int, periodo_id: int, docentes: list<int>, ambientes: list<int>}|null
     */
    public function consultar(User $usuario, int $escuela, int $periodo): ?array
    {
        if ($escuela <= 0 || $periodo <= 0 || ! (new RegistroPolicy)->viewAny($usuario)) {
            return null;
        }

        $escuelas = (new AmbitoConsultaAdministrativa($this->escuelas))->escuelas($usuario);
        if (! in_array($escuela, $escuelas, true)) {
            return null;
        }

        try {
            $proveedor = $this->recursos ?? (app()->bound(ProveedorAlcanceRecursosAcademicos::class) ? app(ProveedorAlcanceRecursosAcademicos::class) : null);
            if ($proveedor === null) {
                return null;
            }
            $alcance = $proveedor->recursosVisibles($usuario, $escuela, $periodo);
        } catch (Throwable $e) {
            report($e);

            return null;
        }

        // Nunca aceptar resultados de otro periodo/escuela ni listas permisivas o ambiguas.
        if (($alcance['escuela_id'] ?? null) !== $escuela || ($alcance['periodo_id'] ?? null) !== $periodo) {
            return null;
        }
        $docentes = $this->idsValidos($alcance['docentes'] ?? null);
        $ambientes = $this->idsValidos($alcance['ambientes'] ?? null);
        if ($docentes === null || $ambientes === null) {
            return null;
        }

        return ['escuela_id' => $escuela, 'periodo_id' => $periodo, 'docentes' => $docentes, 'ambientes' => $ambientes];
    }

    public function incluyeIdentificadorDocente(User $usuario, int $escuela, int $periodo, int $docente): bool
    {
        return $docente > 0 && in_array($docente, $this->consultar($usuario, $escuela, $periodo)['docentes'] ?? [], true);
    }

    public function incluyeIdentificadorAmbiente(User $usuario, int $escuela, int $periodo, int $ambiente): bool
    {
        return $ambiente > 0 && in_array($ambiente, $this->consultar($usuario, $escuela, $periodo)['ambientes'] ?? [], true);
    }

    /** @return list<int>|null */
    private function idsValidos(mixed $ids): ?array
    {
        if (! is_array($ids) || ! array_is_list($ids)) {
            return null;
        }
        foreach ($ids as $id) {
            if (! is_int($id) || $id <= 0) {
                return null;
            }
        }

        return array_values(array_unique($ids));
    }
}
