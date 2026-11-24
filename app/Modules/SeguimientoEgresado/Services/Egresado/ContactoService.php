<?php

namespace App\Modules\SeguimientoEgresado\Services\Egresado;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Contacto del egresado con historial: cada cambio cierra la versión vigente (vigente_hasta) y crea una nueva,
 * de modo que nunca se sobrescribe. Un egresado tiene una sola versión vigente (índice uq_contacto_vigente).
 * La autorización (propio o Secretaría) se verifica antes de llamar a este servicio.
 */
final class ContactoService
{
    /**
     * @param  array{correo_personal:string, celular_whatsapp:string, telefono_fijo?:?string, direccion?:?string}  $datos  ya validados
     * @param  string  $origen  FORMULARIO (el propio egresado) o SECRETARIA
     * @param  int|null  $invitacionId  invitación de contacto inicial que se completa con este envío
     */
    public function guardar(int $egresadoId, array $datos, string $origen = 'FORMULARIO', ?int $invitacionId = null): ResultadoContacto
    {
        if (! in_array($origen, ['FORMULARIO', 'SECRETARIA'], true)) {
            throw new \InvalidArgumentException("Origen de contacto desconocido: {$origen}.");
        }

        $nuevo = [
            'correo_personal' => trim($datos['correo_personal']),
            'celular_whatsapp' => trim($datos['celular_whatsapp']),
            'telefono_fijo' => self::opcional($datos['telefono_fijo'] ?? null),
            'direccion' => self::opcional($datos['direccion'] ?? null),
        ];

        return DB::transaction(function () use ($egresadoId, $nuevo, $origen, $invitacionId) {
            $ahora = CarbonImmutable::now();
            $vigente = DB::table('seguimiento_egresado.contacto_egresado')
                ->where('id_egresado', $egresadoId)->whereNull('vigente_hasta')->lockForUpdate()->first();

            if ($vigente !== null && $this->esIgual($vigente, $nuevo)) {
                $this->completarInvitacion($invitacionId, $egresadoId, $ahora);

                return ResultadoContacto::SinCambios;
            }

            $desde = $ahora;
            if ($vigente !== null) {
                // vigente_hasta debe ser posterior a vigente_desde (restricción de la BD) y la nueva versión empieza donde termina la anterior.
                $anterior = CarbonImmutable::parse($vigente->vigente_desde);
                $desde = $ahora->greaterThan($anterior) ? $ahora : $anterior->addMicrosecond();
                DB::table('seguimiento_egresado.contacto_egresado')->where('id', $vigente->id)->update(['vigente_hasta' => self::ts($desde)]);
            }

            DB::table('seguimiento_egresado.contacto_egresado')->insert($nuevo + [
                'id_egresado' => $egresadoId,
                'vigente_desde' => self::ts($desde),
                'origen_registro' => $origen,
            ]);

            $this->completarInvitacion($invitacionId, $egresadoId, $ahora);

            return $vigente === null ? ResultadoContacto::Creado : ResultadoContacto::Actualizado;
        });
    }

    private function esIgual(object $vigente, array $nuevo): bool
    {
        foreach ($nuevo as $campo => $valor) {
            if (($vigente->{$campo} ?? null) !== $valor) {
                return false;
            }
        }

        return true;
    }

    private function completarInvitacion(?int $invitacionId, int $egresadoId, CarbonImmutable $ahora): void
    {
        if ($invitacionId === null) {
            return;
        }

        DB::table('seguimiento_egresado.invitacion_egresado')
            ->where('id', $invitacionId)->where('id_egresado', $egresadoId)
            ->where('proposito', 'CONTACTO_INICIAL')->where('estado', 'PENDIENTE')
            ->update(['estado' => 'COMPLETADA', 'respondido_en' => self::ts($ahora)]);
    }

    private static function opcional(?string $valor): ?string
    {
        $valor = $valor === null ? '' : trim($valor);

        return $valor === '' ? null : $valor;
    }

    private static function ts(CarbonImmutable $momento): string
    {
        return $momento->format('Y-m-d H:i:s.uP');
    }
}
