<?php

namespace App\Modules\Cotecu\Services;

use App\Shared\Models\Core\PeriodoAcademico;
use App\Shared\Models\Seguridad\Usuario;
use App\Shared\Services\ModuleService;
use Illuminate\Database\Eloquent\Collection;

final class CotecuService extends ModuleService
{
    /**
     * Todos los periodos académicos, del más reciente al más antiguo.
     *
     * @return Collection<int, PeriodoAcademico>
     */
    public function periodos(): Collection
    {
        return PeriodoAcademico::query()
            ->orderByDesc('anio')
            ->orderByDesc('fecha_inicio')
            ->get();
    }

    /**
     * Periodo de contexto de las pantallas: el Activo; si no hay, el más
     * reciente. Así la vista nunca queda sin semestre que mostrar.
     */
    public function periodoActual(): ?PeriodoAcademico
    {
        return PeriodoAcademico::query()
            ->where('estado', 'Activo')
            ->orderByDesc('fecha_inicio')
            ->first()
            ?? $this->periodos()->first();
    }

    /**
     * Solo un periodo Activo habilita la subida de sílabos.
     */
    public function hayPeriodoActivo(): bool
    {
        return PeriodoAcademico::query()->where('estado', 'Activo')->exists();
    }

    /**
     * id_usuario en seguridad.usuario de la cuenta con sesión iniciada.
     *
     * Puente: public.users.email ↔ seguridad.usuario.correo_institucional.
     * Devuelve null cuando la cuenta no tiene fila institucional: la
     * subida debe avisar en vez de reventar con una violación de FK.
     */
    public function idUsuarioActual(): ?int
    {
        $email = auth()->user()?->email;

        if ($email === null) {
            return null;
        }

        $id = Usuario::query()->where('correo_institucional', $email)->value('id_usuario');

        return $id === null ? null : (int) $id;
    }
}
