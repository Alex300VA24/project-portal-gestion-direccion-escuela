<?php

namespace App\Modules\SeguimientoEgresado\Services\Accesos;

final class ResultadoAcceso
{
    private function __construct(
        public readonly MotivoAcceso $motivo,
        public readonly ?InvitacionAcceso $invitacion = null,
    ) {}

    public static function permitido(InvitacionAcceso $invitacion): self
    {
        return new self(MotivoAcceso::Permitido, $invitacion);
    }

    public static function rechazado(MotivoAcceso $motivo): self
    {
        return new self($motivo);
    }

    public function esPermitido(): bool
    {
        return $this->motivo === MotivoAcceso::Permitido;
    }

    /**
     * Lo único que debe guardarse en la sesión del egresado: su alcance queda limitado a su expediente
     * y al vencimiento de la invitación. No incluye matrícula ni documento.
     *
     * @return array{egresado_id:int, invitacion_id:int, campania_id:?int, proposito:string, vence_en:string}
     */
    public function datosDeSesion(): array
    {
        return [
            'egresado_id' => $this->invitacion->idEgresado,
            'invitacion_id' => $this->invitacion->id,
            'campania_id' => $this->invitacion->idCampania,
            'proposito' => $this->invitacion->proposito,
            'vence_en' => $this->invitacion->expiraEn->toIso8601String(),
        ];
    }
}
