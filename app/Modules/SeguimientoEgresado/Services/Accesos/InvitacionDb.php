<?php

namespace App\Modules\SeguimientoEgresado\Services\Accesos;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Lectura de invitacion_egresado sobre el esquema PostgreSQL vigente, sin modelos propios. */
final class InvitacionDb implements InvitacionRepositorio
{
    public function buscarPorHash(string $hash): ?InvitacionAcceso
    {
        $fila = DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->where('i.token_hash', $hash)
            ->select('i.id', 'i.id_egresado', 'i.id_campania', 'i.proposito', 'i.estado', 'i.expira_en', 'e.codigo_matricula', 'e.numero_documento')
            ->first();

        if ($fila === null) {
            return null;
        }

        return new InvitacionAcceso(
            id: (int) $fila->id,
            idEgresado: (int) $fila->id_egresado,
            idCampania: $fila->id_campania === null ? null : (int) $fila->id_campania,
            proposito: $fila->proposito,
            estado: $fila->estado,
            expiraEn: CarbonImmutable::parse($fila->expira_en),
            codigoMatricula: $fila->codigo_matricula,
            numeroDocumento: $fila->numero_documento,
        );
    }
}
