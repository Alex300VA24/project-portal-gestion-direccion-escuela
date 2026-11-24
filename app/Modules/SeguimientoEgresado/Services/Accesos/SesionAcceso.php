<?php

namespace App\Modules\SeguimientoEgresado\Services\Accesos;

use App\Modules\SeguimientoEgresado\Models\UsuarioInterno;
use App\Modules\SeguimientoEgresado\Support\Accesos\Identidad;
use App\Modules\SeguimientoEgresado\Support\Accesos\Perfil;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Http\Request;

/**
 * Identidades con las que se usa el módulo en una petición. Pueden convivir dos:
 *  - interna: el usuario que inició sesión en el portal, si su rol es de Secretaría o Dirección;
 *  - egresado: quien abrió el enlace personal de su invitación (guardada en la sesión, hasta que el enlace venza).
 * Conviven porque la Secretaría puede abrir en su navegador el enlace de un egresado para ver lo que él ve.
 */
final class SesionAcceso
{
    private const CLAVE = 'seguimiento-egresado.identidad';

    private const MEMO_INTERNA = 'seguimiento-egresado.identidad-interna';

    public function __construct(
        private readonly Session $sesion,
        private readonly ?Request $peticion = null,
    ) {}

    public static function desde(Request $request): self
    {
        return new self($request->session(), $request);
    }

    public function iniciarEgresado(ResultadoAcceso $resultado): void
    {
        if (! $resultado->esPermitido()) {
            throw new \InvalidArgumentException('Solo un acceso permitido puede abrir la sesión del egresado.');
        }

        $datos = $resultado->datosDeSesion();
        $this->sesion->regenerate();
        $this->sesion->put(self::CLAVE, [
            'perfil' => Perfil::Egresado->value,
            'egresado_id' => $datos['egresado_id'],
            'invitacion_id' => $datos['invitacion_id'],
            'vence_en' => $datos['vence_en'],
        ]);
    }

    /** Identidad del egresado que entró con su enlace; si la sesión ya venció, la elimina y devuelve null. */
    public function egresado(): ?Identidad
    {
        $datos = $this->sesion->get(self::CLAVE);

        if (! is_array($datos) || ($datos['perfil'] ?? null) !== Perfil::Egresado->value) {
            return null;
        }

        $identidad = new Identidad(
            perfil: Perfil::Egresado,
            egresadoId: $datos['egresado_id'] ?? null,
            invitacionId: $datos['invitacion_id'] ?? null,
            venceEn: isset($datos['vence_en']) ? CarbonImmutable::parse($datos['vence_en']) : null,
        );

        if ($identidad->haVencido()) {
            $this->sesion->forget(self::CLAVE);

            return null;
        }

        return $identidad;
    }

    /** Identidad del personal: el usuario del portal con rol de Secretaría o Dirección. Null si no inició sesión o no tiene ese rol. */
    public function interna(): ?Identidad
    {
        $usuario = $this->peticion?->user();

        if ($usuario === null || ! method_exists($usuario, 'getRoleNames')) {
            return null;
        }

        if ($this->peticion->attributes->has(self::MEMO_INTERNA)) {
            return $this->peticion->attributes->get(self::MEMO_INTERNA);
        }

        $perfil = Perfil::desdeRolesDelPortal($usuario->getRoleNames()->all());
        $identidad = $perfil === null ? null : new Identidad($perfil, usuarioId: UsuarioInterno::delPortal($usuario, $perfil)->id);

        $this->peticion->attributes->set(self::MEMO_INTERNA, $identidad);

        return $identidad;
    }

    /** @return list<Identidad> la del personal primero y luego la del egresado, las que existan. */
    public function todas(): array
    {
        return array_values(array_filter([$this->interna(), $this->egresado()]));
    }

    /** La identidad principal: la del personal si inició sesión; si no, la del egresado. */
    public function actual(): ?Identidad
    {
        return $this->interna() ?? $this->egresado();
    }

    public function cerrar(): void
    {
        $this->sesion->forget(self::CLAVE);
    }
}
