<?php

namespace App\Modules\SeguimientoEgresado\Services;

use App\Modules\SeguimientoEgresado\Services\Egresado\HistorialService;
use Illuminate\Support\Facades\DB;

/**
 * Una respuesta completa, para que la Secretaría (egresados) o el personal con acceso a la encuesta empresarial (empresas) la revise
 * sin salir de la pantalla en que está. Quien llama debe haber comprobado ya el permiso.
 */
final class RespuestasParaPersonal
{
    public function __construct(private readonly HistorialService $historial) {}

    /**
     * La respuesta de un egresado a una invitación de seguimiento; null si la invitación no existe o todavía no respondió.
     *
     * @return array{egresado: array<string, mixed>, m: array<string, mixed>}|null
     */
    public function deEgresado(int $invitacionId): ?array
    {
        $fila = DB::table('seguimiento_egresado.invitacion_egresado as i')
            ->join('seguimiento_egresado.egresado as e', 'e.id', '=', 'i.id_egresado')
            ->leftJoin('seguimiento_egresado.cohorte as c', 'c.id', '=', 'e.id_cohorte')
            ->join('seguimiento_egresado.medicion_laboral as m', 'm.id_invitacion', '=', 'i.id')
            ->where('i.id', $invitacionId)
            ->first(['i.id_egresado', 'm.id as id_medicion', 'e.nombres', 'e.apellido_paterno', 'e.apellido_materno', 'e.codigo_matricula', 'c.anio_ingreso']);

        $medicion = $fila === null ? null : $this->historial->medicion((int) $fila->id_egresado, (int) $fila->id_medicion);
        if ($medicion === null) {
            return null;
        }

        return [
            'egresado' => [
                'nombre' => trim($fila->apellido_paterno.' '.$fila->apellido_materno.', '.$fila->nombres),
                'matricula' => $fila->codigo_matricula, 'promocion' => $fila->anio_ingreso,
            ],
            'm' => $medicion,
        ];
    }

    /**
     * La respuesta de una empresa (opinión sobre un egresado o encuesta general); null si no existe o todavía no respondió.
     *
     * @return array<string, mixed>|null
     */
    public function deEmpresa(int $invitacionId): ?array
    {
        $r = DB::table('seguimiento_egresado.invitacion_empresa as ie')
            ->join('seguimiento_egresado.respuesta_empresa as r', 'r.id_invitacion_empresa', '=', 'ie.id')
            ->join('seguimiento_egresado.campania_seguimiento as c', 'c.id', '=', 'ie.id_campania')
            ->leftJoin('seguimiento_egresado.cat_nivel_satisfaccion as ns', 'ns.id', '=', 'r.id_nivel_satisfaccion')
            ->leftJoin('seguimiento_egresado.cat_sector_empresa as se', 'se.id', '=', 'r.id_sector_empresa')
            ->leftJoin('seguimiento_egresado.egresado as e', 'e.id', '=', 'ie.id_egresado')
            ->leftJoin('seguimiento_egresado.cohorte as co', 'co.id', '=', 'e.id_cohorte')
            ->where('ie.id', $invitacionId)
            ->first([
                'r.id', 'r.fecha_respuesta', 'r.cargo_informante', 'r.organizacion_informada', 'r.comentario', 'ie.contexto', 'ie.organizacion', 'ie.contacto_empresa',
                'c.nombre as campania', 'c.periodo', 'ns.nombre as satisfaccion', 'se.nombre as sector',
                'e.nombres', 'e.apellido_paterno', 'e.apellido_materno', 'co.anio_ingreso',
            ]);

        if ($r === null) {
            return null;
        }

        $habilidades = DB::table('seguimiento_egresado.respuesta_empresa_habilidad as rh')
            ->join('seguimiento_egresado.cat_habilidad as h', 'h.id', '=', 'rh.id_habilidad')
            ->where('rh.id_respuesta', $r->id)->orderBy('h.nombre')
            ->get(['rh.tipo', 'h.codigo', 'h.nombre', 'rh.detalle_otro'])->map(fn ($h) => (array) $h);

        return [
            'contexto' => $r->contexto, 'campania' => $r->campania, 'periodo' => $r->periodo, 'fecha_respuesta' => $r->fecha_respuesta,
            'organizacion' => $r->organizacion_informada ?: $r->organizacion, 'cargo' => $r->cargo_informante, 'sector' => $r->sector,
            'satisfaccion' => $r->satisfaccion, 'comentario' => $r->comentario,
            'egresado' => $r->nombres === null ? null : trim($r->apellido_paterno.' '.$r->apellido_materno.', '.$r->nombres).($r->anio_ingreso ? ' · Promoción '.$r->anio_ingreso : ''),
            'demandas' => $habilidades->where('tipo', 'DEMANDA')->values()->all(),
            'brechas' => $habilidades->where('tipo', 'BRECHA')->values()->all(),
            'otras' => $habilidades->whereNotIn('tipo', ['DEMANDA', 'BRECHA'])->values()->all(),
        ];
    }
}
