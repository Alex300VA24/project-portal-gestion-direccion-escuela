<?php

namespace App\Modules\GestionHorarios\Services;

use App\Models\User;
use App\Modules\GestionHorarios\Contracts\ProveedorEscuelasAutorizadas;
use Throwable;

/** Control centralizado para consultas administrativas; no autoriza recursos globales. */
final class AmbitoConsultaAdministrativa
{
    public function __construct(private readonly ?ProveedorEscuelasAutorizadas $proveedor = null) {}

    public function escuelas(User $usuario): array
    {
        try {
            if (! $usuario->can('gestion-horarios.view')) {
                return [];
            }
            $proveedor = $this->proveedor ?? (app()->bound(ProveedorEscuelasAutorizadas::class) ? app(ProveedorEscuelasAutorizadas::class) : null);
            $escuelas = $proveedor?->escuelasVigentes($usuario) ?? [];
        } catch (Throwable $e) {
            // Un error de Seguridad/Core nunca equivale a autorización abierta.
            report($e);

            return [];
        }
        if (! array_is_list($escuelas) || ! $escuelas || count(array_filter($escuelas, fn ($id) => is_int($id) && $id > 0)) !== count($escuelas)) {
            return [];
        }

        return array_values(array_unique($escuelas));
    }

    public function exigirEscuela(User $usuario): void
    {
        abort_if($this->escuelas($usuario) === [], 403, 'No se ha verificado la escuela autorizada de su cuenta.');
    }

    public function denegarRecursoGlobal(User $usuario): never
    {
        $this->exigirEscuela($usuario);
        abort(403, 'Esta consulta no está disponible hasta verificar el ámbito autorizado de sus recursos académicos.');
    }
}
